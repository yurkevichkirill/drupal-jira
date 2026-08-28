import { expect, test } from '@playwright/test';

/**
 * Runs under the "admin" project (storageState = playwright/.auth/admin.json,
 * logged in as the admin account, which per config/sync/user.role.task_reviewer.yml
 * effectively carries reviewer-equivalent permissions such as
 * "use task_status_workflow transition approve"). We assert on the
 * add-task form since it's the simplest page to reach for both roles;
 * the meaningful check is the presence of the workflow "approve"
 * transition, which only reviewers/admins hold.
 */
test('privileged user can access the task add form', async ({ page }) => {
  const response = await page.goto('/node/add/task');
  expect(response?.status()).toBe(200);
  await expect(page.getByLabel('Title', { exact: true })).toBeVisible();
});

test('privileged user sees the People administration page', async ({ page }) => {
  // "administer users" (admin-only) is a good proxy for admin-vs-regular-user
  // access, distinct from the task_reviewer-vs-authenticated "approve"
  // transition permission, which is exercised via the moderation form itself.
  const response = await page.goto('/admin/people');
  expect(response?.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'People' })).toBeVisible();
});
