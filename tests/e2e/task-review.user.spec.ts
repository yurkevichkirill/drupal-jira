import { expect, test } from '@playwright/test';

/**
 * Runs under the "user" project (storageState = playwright/.auth/user.json),
 * logged in as a plain authenticated user with NO task_reviewer role
 * (config/sync/user.role.authenticated.yml grants task creation/editing
 * but not "administer users" or the "approve" workflow transition).
 */
test('regular authenticated user can create a task', async ({ page }) => {
  const response = await page.goto('/node/add/task');
  expect(response?.status()).toBe(200);
  await expect(page.getByLabel('Title', { exact: true })).toBeVisible();
});

test('regular authenticated user is denied the People administration page', async ({ page }) => {
  const response = await page.goto('/admin/people');
  expect(response?.status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Access denied' })).toBeVisible();
});
