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
 * Reports the aggregated time figures of a project.
 */
#[ReportGenerator(
  id: 'project_summary',
  label: 'Project summary',
)]
final class ProjectSummaryReport extends PluginBase implements ReportGeneratorInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a ProjectSummaryReport plugin.
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
   */
  public function generate(NodeInterface $project): array {
    return $this->taskStatService->getProjectStats($project);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

}
