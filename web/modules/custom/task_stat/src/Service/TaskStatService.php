<?php

declare(strict_types=1);

namespace Drupal\task_stat\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;

/**
 * Calculates logged time and estimate figures for tasks and projects.
 */
final class TaskStatService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a TaskStatService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TranslationInterface $string_translation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Sums the hours logged against a single task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task the time logs refer to.
   *
   * @return float
   *   The total number of hours logged for the task, 0.0 when the task has no
   *   time logs yet.
   */
  public function getLoggedHours(NodeInterface $task): float {
    $storage = $this->entityTypeManager->getStorage('time_log');
    $timeLogs = $storage->loadByProperties(['task' => $task->id()]);

    $total = 0.0;
    /** @var \Drupal\time_tracking\TimeLogInterface $timeLog */
    foreach ($timeLogs as $timeLog) {
      $total += (float) ($timeLog->get('hours')->value ?? 0);
    }

    return $total;
  }

  /**
   * Returns how much of the task estimate is left.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task the estimate is read from.
   *
   * @return float
   *   The estimate minus the logged hours. A negative value means more time
   *   was logged than estimated.
   */
  public function getRemainingEstimate(NodeInterface $task): float {
    $estimate = (float) ($task->get('field_estimate')->value ?? 0);

    return $estimate - $this->getLoggedHours($task);
  }

  /**
   * Aggregates time and estimate figures over all tasks of a project.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project the tasks refer to through field_project.
   *
   * @return array
   *   An associative array with the following keys:
   *   - total_tasks: (int) The number of tasks in the project.
   *   - done_tasks: (int) The number of tasks in the 'done' moderation state.
   *   - total_estimate: (float) The sum of field_estimate over all tasks.
   *   - total_logged: (float) The sum of the hours logged against all tasks.
   *   - remaining: (float) What is left of the estimate of the project. A
   *     negative value means more time was logged than estimated.
   *   - over_estimate_tasks: (int) The number of tasks with more hours logged
   *     than estimated.
   */
  public function getProjectStats(NodeInterface $project): array {
    /** @var \Drupal\node\NodeInterface[] $tasks */
    $tasks = $this->getProjectTasks($project);

    $total_tasks = count($tasks);
    $done_tasks = 0;
    $total_estimate = 0;
    $total_logged = 0;
    $over_estimate_tasks = 0;

    foreach ($tasks as $task) {
      $estimate = (float) ($task->get('field_estimate')->value ?? 0);
      $logged = $this->getLoggedHours($task);

      $total_estimate += $estimate;
      $total_logged += $logged;

      if ($task->hasField('moderation_state') && $task->get('moderation_state')->value === 'done') {
        $done_tasks++;
      }

      if ($estimate - $logged < 0) {
        $over_estimate_tasks++;
      }
    }

    return [
      'total_tasks' => $total_tasks,
      'done_tasks' => $done_tasks,
      'total_estimate' => $total_estimate,
      'total_logged' => $total_logged,
      'remaining' => $total_estimate - $total_logged,
      'over_estimate_tasks' => $over_estimate_tasks,
    ];

  }

  /**
   * Renders how many tasks of a project are done, as a sentence.
   *
   * Shared between the project statistics block (full page load) and the
   * Kanban board's drag-and-drop status update (AJAX), so the two never
   * report the figures with different wording.
   *
   * @param array $stats
   *   The stats array returned by self::getProjectStats(), or any array with
   *   'done_tasks' and 'total_tasks' keys.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A string such as "3 of 5 done".
   */
  public function formatTasksSummary(array $stats): TranslatableMarkup {
    return $this->t('@done of @total done', [
      '@done' => $stats['done_tasks'],
      '@total' => $stats['total_tasks'],
    ]);
  }

  /**
   * Loads all tasks that belong to a project.
   *
   * @param \Drupal\node\NodeInterface $project
   *   The project the tasks refer to through field_project.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The task nodes, keyed by node ID.
   */
  public function getProjectTasks(NodeInterface $project): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'task')
      ->condition('field_project', $project->id())
      ->execute();

    $tasks = $storage->loadMultiple($ids);

    return $tasks;
  }

}
