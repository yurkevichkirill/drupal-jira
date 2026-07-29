<?php

declare(strict_types=1);

namespace Drupal\task_board\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->time = $container->get('datetime.time');
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

    $node->set(self::STATUS_FIELD, $status);
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId((int) $this->currentUser()->id());
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionLogMessage('Status changed on the Kanban board.');
    $node->save();

    return new JsonResponse([
      'nid' => (int) $node->id(),
      'status' => $status,
    ]);
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
