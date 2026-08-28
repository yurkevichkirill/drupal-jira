import { expect, test } from '@playwright/test';
import { uniqueTitle } from './helpers';

/**
 * Runs under the "admin" project. Project creation/editing has no dedicated
 * permission granted to any role in config/sync/user.role.*.yml, so only the
 * superuser account can reach /node/add/project and /node/{id}/edit for a
 * project (see tests/e2e/README.md).
 */
test.describe('Project creation and editing', () => {
  test('a Kanban project can be created and keeps its default type after reload', async ({ page }) => {
    const title = uniqueTitle('Kanban Project');

    await page.goto('/node/add/project');
    await page.getByLabel('Title', { exact: true }).fill(title);
    // field.field.node.project.field_project_type.yml sets a 'kanban'
    // default_value; the form must not be touched for this to be a real
    // assertion about the default rather than an explicit selection.
    await expect(page.getByLabel('Project Type', { exact: true })).toHaveValue('kanban');
    await page.getByRole('button', { name: 'Save' }).first().click();

    await expect(page).toHaveURL(/\/node\/\d+$/);
    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    await expect(page.getByText('Kanban', { exact: true })).toBeVisible();

    // Assert the persisted value survives a fresh navigation, not just the
    // post-save render of the same request.
    const nodeUrl = page.url();
    await page.goto('about:blank');
    await page.goto(nodeUrl);
    await expect(page.getByText('Kanban', { exact: true })).toBeVisible();
  });

  test('a project can be switched from Kanban to Scrum and the change persists', async ({ page }) => {
    const title = uniqueTitle('Scrum Switch Project');

    await page.goto('/node/add/project');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nodeUrl = page.url();

    await page.goto(`${nodeUrl}/edit`);
    await page.getByLabel('Project Type', { exact: true }).selectOption('scrum');
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(nodeUrl);
    await expect(page.getByText('Scrum', { exact: true })).toBeVisible();

    await page.goto('about:blank');
    await page.goto(nodeUrl);
    await expect(page.getByText('Scrum', { exact: true })).toBeVisible();
    await expect(page.getByText('Kanban', { exact: true })).not.toBeVisible();
  });
});
