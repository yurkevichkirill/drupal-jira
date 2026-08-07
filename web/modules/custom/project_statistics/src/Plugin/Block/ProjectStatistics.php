<?php

declare(strict_types=1);

namespace Drupal\project_statistics\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\task_stat\Service\TaskStatService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the aggregated time figures of the project of the current page.
 */
#[Block(
  id: 'project_statistics',
  admin_label: new TranslatableMarkup('Project statistics'),
  category: new TranslatableMarkup('DrupalJira'),
)]
final class ProjectStatistics extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ProjectStatistics block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\task_stat\Service\TaskStatService $taskStatService
   *   The task statistics service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly TaskStatService $taskStatService,
    protected readonly RouteMatchInterface $routeMatch,
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
      $container->get('drupaljira.task_stat'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $project = $this->getProjectFromRoute();

    if (!$project instanceof NodeInterface) {
      return [
        '#markup' => $this->t('Statistics are only available on project and task pages.'),
      ];
    }

    $stats = $this->taskStatService->getProjectStats($project);

    return [
      '#title' => $this->t('Statistics of "@project"', ['@project' => $project->label()]),
      'stats' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Tasks: @value', ['@value' => $stats['total_tasks']]),
          $this->t('Done: @value', ['@value' => $stats['done_tasks']]),
          $this->t('Total estimate: @value h', ['@value' => number_format((float) $stats['total_estimate'], 2)]),
          $this->t('Total logged: @value h', ['@value' => number_format((float) $stats['total_logged'], 2)]),
          $this->t('Over estimate: @value', ['@value' => $stats['over_estimate_tasks']]),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // The rendered figures depend on the node of the current route.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Any task or time log change alters the aggregated numbers.
    $tags = ['node_list:task', 'time_log_list'];

    $project = $this->getProjectFromRoute();
    if ($project instanceof NodeInterface) {
      $tags = Cache::mergeTags($tags, $project->getCacheTags());
    }

    return Cache::mergeTags(parent::getCacheTags(), $tags);
  }

  /**
   * Resolves the project the current page belongs to.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The project node itself on a project page, the referenced project on a
   *   task page, NULL on any other route.
   */
  private function getProjectFromRoute(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    if ($node->bundle() === 'project') {
      return $node;
    }

    if ($node->bundle() === 'task' && $node->hasField('field_project')) {
      $project = $node->get('field_project')->entity;

      return $project instanceof NodeInterface ? $project : NULL;
    }

    return NULL;
  }

}
