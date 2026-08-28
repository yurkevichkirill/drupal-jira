<?php

declare(strict_types=1);

namespace Drupal\task_board\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\duration_formatter\Service\DurationFormatter;
use Drupal\node\NodeInterface;
use Drupal\task_stat\Service\TaskStatService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Saves the column a task card was dropped into on the Kanban board.
 */
final class TaskStatusController extends ControllerBase {

  /**
   * The name of the field the board columns are built from.
   */
  private const STATUS_FIELD = 'field_status';

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * The task statistics service.
   *
   * @var \Drupal\task_stat\Service\TaskStatService
   */
  private TaskStatService $taskStat;

  /**
   * The duration formatter service.
   *
   * @var \Drupal\duration_formatter\Service\DurationFormatter
   */
  private DurationFormatter $durationFormatter;

  /**
   * The content moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  private ModerationInformationInterface $moderationInfo;

  /**
   * The content moderation transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  private StateTransitionValidationInterface $transitionValidation;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->time = $container->get('datetime.time');
    $instance->taskStat = $container->get('drupaljira.task_stat');
    $instance->durationFormatter = $container->get('drupaljira.duration_formatter');
    $instance->moderationInfo = $container->get('content_moderation.moderation_information');
    $instance->transitionValidation = $container->get('content_moderation.state_transition_validation');
    return $instance;
  }

  /**
   * Writes a new status value to a task node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The task the dropped card represents.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request carrying a {"status": "..."} JSON payload.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The stored node id and status.
   */
  public function update(NodeInterface $node, Request $request): JsonResponse {
    if ($node->bundle() !== 'task' || !$node->hasField(self::STATUS_FIELD)) {
      throw new BadRequestHttpException('Only task nodes can be moved on the board.');
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    $status = is_array($payload) && isset($payload['status']) ? (string) $payload['status'] : '';
    if (!in_array($status, $this->allowedStatuses($node), TRUE)) {
      throw new BadRequestHttpException('Unknown task status.');
    }

    // Column placement mirrors the moderation state of the task, so a card
    // can only be dropped where the current user is allowed to move the
    // workflow: 'node.update' access alone is too coarse, since roles here
    // are restricted to individual transitions (@see
    // config/sync/user.role.task_reviewer.yml).
    if ($this->moderationInfo->isModeratedEntity($node) && !$this->isTransitionAllowed($node, $status)) {
      throw new AccessDeniedHttpException('You are not allowed to move this task to that status.');
    }

    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', $status);
    }
    $node->set(self::STATUS_FIELD, $status);
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId((int) $this->currentUser()->id());
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionLogMessage('Status changed on the Kanban board.');
    $node->save();

    return new JsonResponse([
      'nid' => (int) $node->id(),
      'status' => $status,
      'project_stats' => $this->buildProjectStats($node),
    ]);
  }

  /**
   * Builds the aggregated figures of the project the task belongs to.
   *
   * The board's project statistics block renders these from the database on
   * a full page load; the board itself patches the same numbers into the DOM
   * after a drag-and-drop move, so the two never need to be reloaded to stay
   * in sync with each other.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task that was just moved.
   *
   * @return array|null
   *   The project stats, keyed the same way as
   *   TaskStatService::getProjectStats(), plus pre-formatted display strings;
   *   NULL when the task has no project to report on.
   */
  private function buildProjectStats(NodeInterface $task): ?array {
    if (!$task->hasField('field_project')) {
      return NULL;
    }

    $project = $task->get('field_project')->entity;
    if (!$project instanceof NodeInterface) {
      return NULL;
    }

    $stats = $this->taskStat->getProjectStats($project);

    return $stats + [
      'project_id' => (int) $project->id(),
      'tasks_summary' => (string) $this->taskStat->formatTasksSummary($stats),
      'total_estimate_formatted' => (string) $this->durationFormatter->format((float) $stats['total_estimate']),
      'remaining_estimate_formatted' => (string) $this->durationFormatter->formatRemaining((float) $stats['remaining']),
    ];
  }

  /**
   * Tells whether the current user may move a task to a given state.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The task to be moved.
   * @param string $status
   *   The moderation state the task would move to.
   *
   * @return bool
   *   TRUE when a workflow transition from the task's current state to
   *   $status exists and the current user holds the permission for it.
   */
  private function isTransitionAllowed(NodeInterface $node, string $status): bool {
    $valid_transitions = $this->transitionValidation->getValidTransitions($node, $this->currentUser());

    foreach ($valid_transitions as $transition) {
      if ($transition->to()->id() === $status) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns the status values the field accepts.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The task the status field is read from.
   *
   * @return string[]
   *   The allowed machine values, in the order they are configured.
   */
  private function allowedStatuses(NodeInterface $node): array {
    // The setting is stored as a list of value/label pairs but is handed back
    // as a flat machine name => label map.
    $allowed_values = $node->get(self::STATUS_FIELD)
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('allowed_values');

    return array_map('strval', array_keys((array) $allowed_values));
  }

}
