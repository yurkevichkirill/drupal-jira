<?php

declare(strict_types=1);

namespace Drupal\time_tracking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;
use Drupal\time_tracking\TimeLogInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Demonstrates Entity API CRUD and EntityQuery usage on time log entities.
 */
final class TimeLogDebugController extends ControllerBase {

  /**
   * The time log storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $timeLogStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->timeLogStorage = $container->get('entity_type.manager')->getStorage('time_log');
    return $instance;
  }

  /**
   * Runs a full create, read, update and delete cycle for one time log.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task node the demo time log is attached to.
   *
   * @return array
   *   A render array listing what every step of the cycle did.
   */
  public function crud(NodeInterface $task): array {
    $steps = [];

    // Create.
    $time_log = $this->timeLogStorage->create([
      'task' => $task->id(),
      'uid' => $this->currentUser()->id(),
      'hours' => '1.50',
      'log_date' => (new DrupalDateTime('now'))->format(DateTimeItemInterface::DATE_STORAGE_FORMAT),
      'notes' => 'Created by the Entity API debug page.',
    ]);
    $time_log->save();
    $id = (int) $time_log->id();
    $steps[] = $this->t('Create: saved a new time log, ID @id.', ['@id' => $id]);

    // Read the record back from storage instead of reusing the saved object.
    $this->timeLogStorage->resetCache([$id]);
    $loaded = $this->timeLogStorage->load($id);
    if (!$loaded instanceof TimeLogInterface) {
      $steps[] = $this->t('Read: time log @id could not be loaded.', ['@id' => $id]);
      return $this->stepList($task, $steps);
    }
    $steps[] = $this->t('Read: ID @id, task "@task", hours @hours, log date @date, notes "@notes".', [
      '@id' => $id,
      '@task' => $loaded->get('task')->entity?->label() ?? '',
      '@hours' => $loaded->get('hours')->getString(),
      '@date' => $loaded->get('log_date')->getString(),
      '@notes' => $loaded->get('notes')->getString(),
    ]);

    // Update.
    $old_hours = $loaded->get('hours')->getString();
    $loaded->set('hours', '3.25');
    $loaded->save();

    $this->timeLogStorage->resetCache([$id]);
    $updated = $this->timeLogStorage->load($id);
    $new_hours = $updated instanceof TimeLogInterface ? $updated->get('hours')->getString() : '';
    $steps[] = $this->t('Update: hours changed from @old to @new, re-read from storage.', [
      '@old' => $old_hours,
      '@new' => $new_hours,
    ]);

    // Delete.
    if ($updated instanceof TimeLogInterface) {
      $updated->delete();
    }
    $this->timeLogStorage->resetCache([$id]);
    $steps[] = $this->timeLogStorage->load($id) === NULL
      ? $this->t('Delete: time log @id is gone, load() returns NULL.', ['@id' => $id])
      : $this->t('Delete: time log @id is still in the database.', ['@id' => $id]);

    return $this->stepList($task, $steps);
  }

  /**
   * Lists the time logs of a task, oldest log date first.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task the time logs belong to.
   *
   * @return array
   *   A render array with a table of the found records.
   */
  public function listLogs(NodeInterface $task): array {
    $ids = $this->timeLogStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('task', $task->id())
      ->sort('log_date', 'ASC')
      ->execute();

    $rows = [];
    $time_logs = $this->timeLogStorage->loadMultiple($ids);

    foreach ($ids as $id) {
      $time_log = $time_logs[$id] ?? NULL;
      if (!$time_log instanceof TimeLogInterface) {
        continue;
      }
      $rows[] = [
        $time_log->id(),
        $time_log->get('hours')->getString(),
        $time_log->get('log_date')->getString(),
      ];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Time logs of task "@task", sorted by log date.', ['@task' => $task->label()]),
      '#header' => [$this->t('ID'), $this->t('Hours'), $this->t('Log date')],
      '#rows' => $rows,
      '#empty' => $this->t('This task has no time logs.'),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Shows the total number of hours logged on a task.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task the hours are summed for.
   *
   * @return array
   *   A render array with the total as a single number.
   */
  public function sum(NodeInterface $task): array {
    $alias = NULL;
    $query = $this->timeLogStorage->getAggregateQuery();
    $query->accessCheck(FALSE);
    $query->condition('task', $task->id());
    // The alias the SUM column gets is handed back by reference.
    $query->aggregate('hours', 'SUM', NULL, $alias);
    $result = $query->execute();

    // A task without time logs aggregates to a single NULL row, which is 0.
    $total = is_array($result) && isset($result[0][$alias]) ? (float) $result[0][$alias] : 0.0;

    return [
      '#markup' => $this->t('Total hours logged on task "@task": @total', [
        '@task' => $task->label(),
        '@total' => number_format($total, 2),
      ]),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Wraps the CRUD steps into a render array.
   *
   * @param \Drupal\node\NodeInterface $task
   *   The task the cycle was run for.
   * @param array $steps
   *   The lines describing what happened, in execution order.
   *
   * @return array
   *   A render array with an item list of the steps.
   */
  private function stepList(NodeInterface $task, array $steps): array {
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('CRUD cycle for task "@task" (node @nid)', [
        '@task' => $task->label(),
        '@nid' => $task->id(),
      ]),
      '#items' => $steps,
      '#cache' => ['max-age' => 0],
    ];
  }

}
