<?php

declare(strict_types=1);

namespace Drupal\report_generator\Plugin\ReportGenerator;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\node\NodeInterface;
use Drupal\report_generator\Attribute\ReportGenerator;
use Drupal\report_generator\ReportGeneratorInterface;
use Drupal\task_stat\Service\TaskStatService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the project tasks that logged more hours than estimated.
 */
#[ReportGenerator(
  id: 'overdue_tasks',
  label: 'Overdue tasks',
)]
final class OverdueTasksReport extends PluginBase implements ReportGeneratorInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs an OverdueTasksReport plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\task_stat\Service\TaskStatService $taskStatService
   *   The task statistics service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly TaskStatService $taskStatService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('drupaljira.task_stat')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   A list of overdue tasks. Each item has the following keys:
   *   - id: (int) The task node ID.
   *   - title: (string) The task title.
   *   - overrun: (float) The hours logged beyond the estimate.
   */
  public function generate(NodeInterface $project): array {
    $rows = [];

    foreach ($this->taskStatService->getProjectTasks($project) as $task) {
      $remaining = $this->taskStatService->getRemainingEstimate($task);

      if ($remaining < 0) {
        $rows[] = [
          'id' => (int) $task->id(),
          'title' => $task->label(),
          'overrun' => -$remaining,
        ];
      }
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

}
