<?php

declare(strict_types=1);

namespace Drupal\project_statistics\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly TaskStatService $taskStatService,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $project = $this->getProjectFromRoute();

    if (!$project instanceof NodeInterface) {
      // An empty render array, rather than an explanatory placeholder,
      // because the block is placed globally (region "content", every
      // theme, every route): on any page that is not a project or a task
      // (the front page, a user's profile, the login form, an admin page...)
      // a placeholder here is not "no data yet", it is a permanent, useless
      // fixture of pages the block was never meant for. An empty build
      // makes Drupal's block view builder skip rendering the block
      // (including its otherwise-still-visible title) instead of leaving
      // something wrong-looking behind.
      return [];
    }

    return [
      '#theme' => 'drupaljira_project_stats',
      '#project' => $project,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $project = $this->getProjectFromRoute();
    $tags = [];

    if ($project instanceof NodeInterface) {
      $tags = ['drupaljira_project_stats:' . $project->id()];
      $tags = Cache::mergeTags($tags, $project->getCacheTags());
    }

    return Cache::mergeTags(parent::getCacheTags(), $tags);
  }

  /**
   * Resolves the project the current page belongs to.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The project node itself on a project page, the referenced project on a
   *   task page, the project a Kanban board belongs to, or NULL on any other
   *   route.
   */
  private function getProjectFromRoute(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');

    if ($node instanceof NodeInterface) {
      if ($node->bundle() === 'project') {
        return $node;
      }

      if ($node->bundle() === 'task' && $node->hasField('field_project')) {
        $project = $node->get('field_project')->entity;

        return $project instanceof NodeInterface ? $project : NULL;
      }

      return NULL;
    }

    // The task_board view's "board/%" page does not register its argument as
    // an upcast route parameter (its route is "/board/{arg_0}", with no
    // parameter conversion), so the project only survives on the route as a
    // raw node ID that has to be loaded by hand.
    $arg = $this->routeMatch->getRawParameter('arg_0');
    if ($arg !== NULL) {
      $project = $this->entityTypeManager->getStorage('node')->load($arg);
      if ($project instanceof NodeInterface && $project->bundle() === 'project') {
        return $project;
      }
    }

    return NULL;
  }

}
