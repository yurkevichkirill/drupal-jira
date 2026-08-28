<?php

declare(strict_types=1);

namespace Drupal\project\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Access check for the "Sprints" tab and route on Project nodes.
 */
final class ScrumTabAccessCheck {

  /**
   * Checks access to the Sprints route for a given project node.
   */
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    if ($node->bundle() !== 'project' || !$node->hasField('field_project_type')) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }

    $projectType = $node->get('field_project_type')->value;

    return AccessResult::allowedIf($projectType === 'scrum')
      ->addCacheableDependency($node)
      ->andIf($node->access('view', $account, TRUE));
  }

}
