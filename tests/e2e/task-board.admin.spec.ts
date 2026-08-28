import { expect, test } from '@playwright/test';
import { dragTaskCardToColumn, pickAutocompleteSuggestion, uniqueTitle } from './helpers';

/**
 * Runs under the "admin" project. The superuser bypasses all permission
 * checks, including the task_status_workflow 'approve' transition
 * (review -> done) that config/sync/user.role.task_reviewer.yml is the only
 * named role to hold — this is the one place that transition is exercised.
 */
test.describe('Task board full moderation happy path', () => {
  test('a task can move backlog -> in progress -> review -> done, and the state survives reload', async ({
    page,
  }) => {
    const projectTitle = uniqueTitle('Board Happy Path Project');
    await page.goto('/node/add/project');
    await page.getByLabel('Title', { exact: true }).fill(projectTitle);
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const projectNid = Number(new URL(page.url()).pathname.split('/').pop());

    const taskTitle = uniqueTitle('Happy Path Task');
    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(taskTitle);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), projectTitle);
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const taskNid = Number(new URL(page.url()).pathname.split('/').pop());

    const boardUrl = `/board/${projectNid}`;
    const cardIn = (status: string) => page.locator(`.task-board__column[data-status="${status}"] .task-card`, {
      hasText: taskTitle,
    });

    await page.goto(boardUrl);
    await expect(cardIn('backlog')).toBeVisible();

    for (const [from, to] of [
      ['backlog', 'in_progress'],
      ['in_progress', 'review'],
      ['review', 'done'],
    ] as const) {
      expect(await dragTaskCardToColumn(page, taskNid, to)).toBe(true);
      await expect(cardIn(to)).toBeVisible();
      await expect(cardIn(from)).toHaveCount(0);

      await page.goto('about:blank');
      await page.goto(boardUrl);
      await expect(cardIn(to)).toBeVisible();
    }

    // Also visible from the task's own page after a fresh navigation.
    await page.goto(`/node/${taskNid}`);
    await expect(page.getByText('Done', { exact: true })).toBeVisible();
  });
});
