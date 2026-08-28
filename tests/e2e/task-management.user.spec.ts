import { expect, test } from '@playwright/test';
import { createProjectAsAdmin, pickAutocompleteSuggestion, uniqueTitle } from './helpers';

/**
 * Runs under the "user" project (plain authenticated account, no
 * task_reviewer role). config/sync/user.role.authenticated.yml grants
 * 'create task content' and 'edit any task content', but no project
 * permissions, so every test here creates its own Project fixture through a
 * throwaway admin context first (see helpers.createProjectAsAdmin) and then
 * does the actual task work as the regular user.
 */
test.describe('Task creation and editing', () => {
  test('a task can be created with all principal fields and appears correctly after reload', async ({
    page,
    browser,
  }) => {
    const project = await createProjectAsAdmin(browser, 'Task Fields Project');
    const title = uniqueTitle('Full Fields Task');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), project.title);
    await expect(page.getByLabel('Project', { exact: true })).toHaveValue(new RegExp(`^${project.title} \\(\\d+\\)$`));

    // field.field.node.task.field_status.yml defaults to 'backlog'; leave it
    // untouched so this is a real assertion about the default.
    await expect(page.getByLabel('Status', { exact: true })).toHaveValue('backlog');

    await pickAutocompleteSuggestion(page, page.getByLabel('Assignee', { exact: true }), 'admin');
    await page.getByLabel('Hours', { exact: true }).fill('3');
    await page.getByLabel('Minutes', { exact: true }).fill('30');
    await page.getByLabel('Body', { exact: true }).fill('Created end-to-end by Playwright.');

    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nodeUrl = page.url();

    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    await expect(page.getByText('Backlog', { exact: true })).toBeVisible();
    await expect(page.getByText(project.title)).toBeVisible();
    await expect(page.getByText('Created end-to-end by Playwright.')).toBeVisible();
    // field_estimate uses the 'time_summary' formatter: "3.5 hours (0 hours
    // written off, 3.5 hours left)" for a freshly created, untouched task.
    await expect(page.getByText(/3\.5 hours \(0 hours written off, 3\.5 hours left\)/)).toBeVisible();

    await page.goto('about:blank');
    await page.goto(nodeUrl);
    await expect(page.getByText('Backlog', { exact: true })).toBeVisible();
    await expect(page.getByText(/3\.5 hours \(0 hours written off, 3\.5 hours left\)/)).toBeVisible();
  });

  test('omitting the required Project shows a validation error and creates no task', async ({ page }) => {
    const title = uniqueTitle('Should Not Be Created');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    // The Project field also carries the browser's native HTML5 "required"
    // constraint, which would otherwise block submission client-side before
    // the server (the thing actually under test here) gets a chance to
    // validate anything.
    await page.locator('form#node-task-form').evaluate((form: HTMLFormElement) => {
      form.noValidate = true;
    });
    await page.getByRole('button', { name: 'Save' }).first().click();

    // A validation failure re-renders the same form instead of redirecting.
    await expect(page).toHaveURL('/node/add/task');
    await expect(page.getByText('Project field is required.')).toBeVisible();

    await page.goto('/admin/content');
    await expect(page.getByRole('link', { name: title })).toHaveCount(0);
  });

  test('editing a task persists a changed assignee, estimate, and description after reload', async ({
    page,
    browser,
  }) => {
    const project = await createProjectAsAdmin(browser, 'Task Edit Project');
    const title = uniqueTitle('Editable Task');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), project.title);
    await page.getByLabel('Hours', { exact: true }).fill('1');
    await page.getByLabel('Body', { exact: true }).fill('Original description.');
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nodeUrl = page.url();

    await page.goto(`${nodeUrl}/edit`);
    await pickAutocompleteSuggestion(page, page.getByLabel('Assignee', { exact: true }), 'admin');
    await page.getByLabel('Hours', { exact: true }).fill('2');
    await page.getByLabel('Minutes', { exact: true }).fill('15');
    await page.getByLabel('Body', { exact: true }).fill('Updated description after edit.');
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(nodeUrl);

    await page.goto('about:blank');
    await page.goto(nodeUrl);
    // entity_reference_label only links to the assignee's profile when the
    // viewer has access to view it; a plain authenticated user doesn't, so
    // it renders as text here rather than a link.
    await expect(page.getByText('admin', { exact: true })).toBeVisible();
    await expect(page.getByText('Updated description after edit.')).toBeVisible();
    await expect(page.getByText(/2\.25 hours \(0 hours written off, 2\.25 hours left\)/)).toBeVisible();
    await expect(page.getByText('Original description.')).not.toBeVisible();
  });
});
