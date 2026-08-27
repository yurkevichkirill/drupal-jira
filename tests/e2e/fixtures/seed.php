<?php

/**
 * @file
 * Seeds or cleans up deterministic E2E test data for the Playwright suite.
 *
 * Usage (invoked by tests/e2e/fixtures/seed.mjs, not called directly):
 *   ddev drush scr tests/e2e/fixtures/seed.php seed <user> <pass> <prefix>
 *   ddev drush scr tests/e2e/fixtures/seed.php cleanup.
 *
 * All ephemeral content created by "seed" is tagged with a run-scoped
 * label prefix (e.g. "E2E-1735300000-a1b2c3") so parallel/rerun test
 * runs never collide. "cleanup" removes ANY node/media whose label
 * starts with "E2E-", regardless of which run created it, so no data
 * accumulates across runs even if a previous run crashed before its
 * own cleanup ran.
 *
 * The persistent regular (non-reviewer) test user is idempotent: it is
 * created once, keyed by a fixed username supplied via .env, and its
 * password is (re)set on every seed run so credentials never drift.
 */

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

// phpcs:disable DrupalPractice.CodeAnalysis.VariableAnalysis
// $extra is injected by `drush scr`.
$mode = $extra[0] ?? 'seed';

/**
 * Deletes all nodes, media, and users whose label starts with "E2E-".
 */
function e2e_cleanup(): void {
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nids = $node_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('title', 'E2E-%', 'LIKE')
    ->execute();
  if ($nids) {
    $node_storage->delete($node_storage->loadMultiple($nids));
    echo 'Deleted ' . count($nids) . " E2E node(s).\n";
  }

  $media_storage = \Drupal::entityTypeManager()->getStorage('media');
  $mids = $media_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('name', 'E2E-%', 'LIKE')
    ->execute();
  if ($mids) {
    $media_storage->delete($media_storage->loadMultiple($mids));
    echo 'Deleted ' . count($mids) . " E2E media item(s).\n";
  }

  // Note: the persistent regular test user (E2E_USER_USERNAME) is
  // intentionally NOT deleted here. It is a long-lived, idempotently
  // upserted account (see e2e_ensure_regular_user()), not per-run
  // ephemeral data, and MariaDB's default case-insensitive collation
  // would otherwise match its lowercase "e2e-*" username against the
  // "E2E-%" pattern used for ephemeral content titles and delete it.
  if (\Drupal::entityTypeManager()->hasDefinition('time_log')) {
    $time_log_storage = \Drupal::entityTypeManager()->getStorage('time_log');
    $tlids = $time_log_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('notes', 'E2E-%', 'LIKE')
      ->execute();
    if ($tlids) {
      $time_log_storage->delete($time_log_storage->loadMultiple($tlids));
      echo 'Deleted ' . count($tlids) . " E2E time log(s).\n";
    }
  }
}

/**
 * Ensures a persistent, non-reviewer authenticated test user exists.
 */
function e2e_ensure_regular_user(string $username, string $password): void {
  if (empty($username) || empty($password)) {
    echo "No E2E_USER_USERNAME/PASSWORD supplied, skipping regular user provisioning.\n";
    return;
  }
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  $existing = $user_storage->loadByProperties(['name' => $username]);
  $account = $existing ? reset($existing) : User::create([
    'name' => $username,
    'mail' => $username . '@example.com',
    'status' => 1,
  ]);
  $account->setPassword($password);
  // Explicitly no task_reviewer role: this account represents a plain
  // authenticated user for negative-permission assertions.
  $account->save();
  echo "Ensured regular test user '{$username}' (uid {$account->id()}).\n";
}

/**
 * Creates a Project, a Task on it, a Media image, and a TimeLog entry.
 */
function e2e_seed_content(string $prefix): void {
  $project = Node::create([
    'type' => 'project',
    'title' => $prefix . ' Project',
    'field_body' => ['value' => 'Seeded by Playwright E2E fixtures.', 'format' => 'basic_html'],
    'status' => 1,
  ]);
  $project->save();

  $task = Node::create([
    'type' => 'task',
    'title' => $prefix . ' Task',
    'field_body' => ['value' => 'Seeded task body.', 'format' => 'basic_html'],
    'field_project' => ['target_id' => $project->id()],
    'field_status' => 'backlog',
    'field_estimate' => '2.50',
    'status' => 1,
  ]);
  $task->save();

  if (\Drupal::entityTypeManager()->hasDefinition('time_log')) {
    $time_log_storage = \Drupal::entityTypeManager()->getStorage('time_log');
    $time_log = $time_log_storage->create([
      'task' => $task->id(),
      'hours' => '1.00',
      'notes' => $prefix . ' TimeLog seeded by Playwright fixtures.',
    ]);
    $time_log->save();
  }

  echo "Seeded project {$project->id()}, task {$task->id()} with prefix '{$prefix}'.\n";
}

switch ($mode) {
  case 'seed':
    $username = $extra[1] ?? '';
    $password = $extra[2] ?? '';
    $prefix = $extra[3] ?? ('E2E-' . time());
    e2e_ensure_regular_user($username, $password);
    e2e_seed_content($prefix);
    break;

  case 'cleanup':
    e2e_cleanup();
    break;

  default:
    echo "Unknown mode '{$mode}'. Use 'seed' or 'cleanup'.\n";
}
