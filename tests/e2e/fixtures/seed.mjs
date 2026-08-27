#!/usr/bin/env node
/**
 * Node wrapper around tests/e2e/fixtures/seed.php.
 *
 * Shells out to `ddev drush scr` so the actual entity creation/deletion
 * happens via Drupal's entity API (direct, DB-level access is explicitly
 * allowed for setup/teardown helpers, just not inside test assertions).
 *
 * Usage:
 *   node tests/e2e/fixtures/seed.mjs seed
 *   node tests/e2e/fixtures/seed.mjs cleanup
 */
import { spawnSync } from 'node:child_process';
import { config as loadEnv } from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..', '..', '..');

loadEnv({ path: path.join(repoRoot, '.env') });

const mode = process.argv[2] || 'seed';
if (!['seed', 'cleanup'].includes(mode)) {
  console.error(`Unknown mode "${mode}". Use "seed" or "cleanup".`);
  process.exit(1);
}

const scriptPath = 'tests/e2e/fixtures/seed.php';
const args = ['drush', 'scr', scriptPath, mode];

if (mode === 'seed') {
  const username = process.env.E2E_USER_USERNAME || '';
  const password = process.env.E2E_USER_PASSWORD || '';
  const prefix = `E2E-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  args.push(username, password, prefix);
  console.log(`Seeding E2E data with run prefix: ${prefix}`);
}

const result = spawnSync('ddev', args, {
  cwd: repoRoot,
  stdio: 'inherit',
});

if (result.error) {
  console.error('Failed to invoke ddev drush:', result.error);
  process.exit(1);
}

process.exit(result.status ?? 1);
