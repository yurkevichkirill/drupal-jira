import { expect, test } from '@playwright/test';

/**
 * Smoke test: verifies the application shell renders for an anonymous
 * visitor. Runs under the "anonymous" project (no storageState), so it
 * exercises the real unauthenticated request path.
 */
test('front page renders the application shell', async ({ page }) => {
  await page.goto('/');
  // The page title block is rendered by the active theme on every route
  // and reflects the actual page content (not just <title>/HTTP status).
  await expect(page.locator('#block-drupal-jira-page-title')).toBeVisible();
  // Primary local tasks (e.g. the "View"/"Edit" tabs region) is another
  // stable, app-specific structural element present across the site.
  await expect(page.locator('#block-drupal-jira-local-tasks')).toBeVisible();
});

test('login form is reachable and has the expected fields', async ({ page }) => {
  await page.goto('/user/login');
  await expect(page.getByLabel('Username', { exact: true })).toBeVisible();
  await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
});

test('anonymous visitor is denied access to task creation', async ({ page }) => {
  const response = await page.goto('/node/add/task');
  expect(response?.status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Access denied' })).toBeVisible();
});
