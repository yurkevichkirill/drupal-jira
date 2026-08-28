import { expect, test } from '@playwright/test';
import { createProjectAsAdmin, dragTaskCardToColumn, pickAutocompleteSuggestion, uniqueTitle } from './helpers';

/**
 * Runs under the "user" project (plain authenticated account, no
 * task_reviewer role). config/sync/user.role.authenticated.yml grants the
 * 'start_progress', 'submit_review', 'reopen', and 'create_new_backlog'
 * task_status_workflow transitions but NOT 'approve' (review -> done),
 * which is exclusive to task_reviewer/admin
 * (config/sync/user.role.task_reviewer.yml).
 *
 * Every board move here goes through the real drag-and-drop handler (see
 * helpers.dragTaskCardToColumn) so it exercises the same permission check
 * (TaskStatusController::isTransitionAllowed) a real user's mouse would.
 */
test.describe('Task board and workflow transitions', () => {
  test('a newly created task appears in the correct project/status column and opens from its card', async ({
    page,
    browser,
  }) => {
    const project = await createProjectAsAdmin(browser, 'Board Visibility Project');
    const title = uniqueTitle('Board Card Task');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), project.title);
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);

    await page.goto(`/board/${project.nid}`);
    const backlogColumn = page.locator('.task-board__column[data-status="backlog"]');
    const card = backlogColumn.locator('.task-card', { hasText: title });
    await expect(card).toBeVisible();
    // The task must not also show up in another column.
    await expect(
      page.locator('.task-board__column[data-status="in_progress"] .task-card', { hasText: title }),
    ).toHaveCount(0);

    await card.getByRole('link', { name: title }).click();
    const dialog = page.getByRole('dialog').filter({ hasText: title });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText('Backlog', { exact: true })).toBeVisible();
  });

  test('the user can move a task through backlog -> in progress -> review -> reopen', async ({ page, browser }) => {
    const project = await createProjectAsAdmin(browser, 'Board Workflow Project');
    const title = uniqueTitle('Workflow Task');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), project.title);
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nid = Number(new URL(page.url()).pathname.split('/').pop());

    await page.goto(`/board/${project.nid}`);
    await expect(page.locator('.task-board__column[data-status="backlog"] .task-card', { hasText: title })).toBeVisible();

    expect(await dragTaskCardToColumn(page, nid, 'in_progress')).toBe(true);
    await expect(page.locator('.task-board__column[data-status="in_progress"] .task-card', { hasText: title })).toBeVisible();

    // Persisted after a fresh navigation, not just the in-page DOM move.
    await page.goto('about:blank');
    await page.goto(`/board/${project.nid}`);
    await expect(page.locator('.task-board__column[data-status="in_progress"] .task-card', { hasText: title })).toBeVisible();

    expect(await dragTaskCardToColumn(page, nid, 'review')).toBe(true);
    await page.goto('about:blank');
    await page.goto(`/board/${project.nid}`);
    await expect(page.locator('.task-board__column[data-status="review"] .task-card', { hasText: title })).toBeVisible();

    // 'reopen' (review/done -> in_progress) is granted to 'authenticated'.
    expect(await dragTaskCardToColumn(page, nid, 'in_progress')).toBe(true);
    await page.goto('about:blank');
    await page.goto(`/board/${project.nid}`);
    await expect(page.locator('.task-board__column[data-status="in_progress"] .task-card', { hasText: title })).toBeVisible();
  });

  test('the user cannot approve a task from review to done (no permission)', async ({ page, browser }) => {
    const project = await createProjectAsAdmin(browser, 'Board Denied Approve Project');
    const title = uniqueTitle('Needs Reviewer Task');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), project.title);
    await page.getByLabel('Moderation state', { exact: true }).selectOption('review');
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nid = Number(new URL(page.url()).pathname.split('/').pop());

    await page.goto(`/board/${project.nid}`);
    await expect(page.locator('.task-board__column[data-status="review"] .task-card', { hasText: title })).toBeVisible();

    expect(await dragTaskCardToColumn(page, nid, 'done')).toBe(false);
    // The card must stay in Review, not move to Done, even optimistically.
    await expect(page.locator('.task-board__column[data-status="review"] .task-card', { hasText: title })).toBeVisible();
    await expect(page.locator('.task-board__column[data-status="done"] .task-card', { hasText: title })).toHaveCount(0);

    await page.goto('about:blank');
    await page.goto(`/board/${project.nid}`);
    await expect(page.locator('.task-board__column[data-status="review"] .task-card', { hasText: title })).toBeVisible();
  });
});
