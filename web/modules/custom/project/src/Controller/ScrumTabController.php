<?php

declare(strict_types=1);

namespace Drupal\project\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Stub controller for the Sprints tab on Project nodes.
 */
final class ScrumTabController extends ControllerBase {

  /**
   * Builds the Sprints stub page.
   */
  public function view(NodeInterface $node): array {
    return [
      '#markup' => $this->t('Sprints for project: @title', ['@title' => $node->label()]),
    ];
  }

  /**
   * Keeps the project's own title on the Sprints tab instead of "Sprints".
   */
  public function title(NodeInterface $node): string {
    return $node->label();
  }

}
