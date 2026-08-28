import { expect, test } from '@playwright/test';
import { createProjectAsAdmin, pickAutocompleteSuggestion, uniqueTitle } from './helpers';

/**
 * Runs under the "user" project. config/sync/user.role.authenticated.yml
 * grants 'create time_log', so a plain authenticated user can log time
 * against a task through /task/{task}/log-time
 * (see web/modules/custom/time_tracking/src/Form/TaskLogTimeForm.php)
 * without needing the task_reviewer role.
 */
test.describe('Time tracking', () => {
  test('a TimeLog submitted through the UI updates the task\'s displayed remaining time', async ({
    page,
    browser,
  }) => {
    const project = await createProjectAsAdmin(browser, 'Time Tracking Project');
    const title = uniqueTitle('Time Tracking Task');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), project.title);
    await page.getByLabel('Hours', { exact: true }).fill('3');
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nodeUrl = page.url();
    const nid = Number(new URL(nodeUrl).pathname.split('/').pop());

    // Before logging any time: full estimate is still all "remaining".
    await expect(page.getByText(/3 hours \(0 hours written off, 3 hours left\)/)).toBeVisible();

    await page.goto(`/task/${nid}/log-time`);
    await page.getByLabel('Hours', { exact: true }).fill('1.5');
    await page.getByLabel('Notes', { exact: true }).fill(uniqueTitle('logged via Playwright'));
    await page.getByRole('button', { name: 'Log time' }).click();

    await expect(page).toHaveURL(nodeUrl);
    // The "Logged X h." status message is a transient toast (gin theme
    // auto-dismisses it), so only the persisted, reloadable aggregate below
    // is asserted on — a flash message fading before the assertion runs is
    // not a real failure.
    await expect(page.getByText(/3 hours \(1\.5 hours written off, 1\.5 hours left\)/)).toBeVisible();

    // The aggregate must survive a fresh navigation, matching the fixture
    // math: 3 h estimate - 1.5 h logged = 1.5 h remaining.
    await page.goto('about:blank');
    await page.goto(nodeUrl);
    await expect(page.getByText(/3 hours \(1\.5 hours written off, 1\.5 hours left\)/)).toBeVisible();

    // Also reflected in the project statistics aggregate.
    await page.goto(`/node/${project.nid}`);
    await expect(page.getByText('1.5 hours', { exact: true })).toBeVisible();
  });

  test('logging more time than estimated requires a reason and is reflected as an overrun', async ({
    page,
    browser,
  }) => {
    const project = await createProjectAsAdmin(browser, 'Overrun Project');
    const title = uniqueTitle('Overrun Task');

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(title);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), project.title);
    await page.getByLabel('Hours', { exact: true }).fill('1');
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nodeUrl = page.url();
    const nid = Number(new URL(nodeUrl).pathname.split('/').pop());

    await page.goto(`/task/${nid}/log-time`);
    await page.getByLabel('Hours', { exact: true }).fill('2');
    await page.getByLabel('I am logging more than estimated').check();
    // The reason field only becomes required client-side once the checkbox
    // above is checked (#states); bypass the browser's own native
    // constraint validation so the server-side check in
    // TaskLogTimeForm::validateForm() is what's actually being exercised.
    await page.locator('form').filter({ has: page.getByRole('button', { name: 'Log time' }) }).evaluate((form: HTMLFormElement) => {
      form.noValidate = true;
    });
    await page.getByRole('button', { name: 'Log time' }).click();
    // TaskLogTimeForm::validateForm requires a reason once the checkbox is
    // checked, and does not create the TimeLog until it is supplied.
    await expect(page.getByText('Please explain why you log more than estimated.')).toBeVisible();
    await expect(page).toHaveURL(`/task/${nid}/log-time`);

    await page.getByLabel('Reason for exceeding the estimate').fill('Scope grew mid-task.');
    await page.getByRole('button', { name: 'Log time' }).click();
    await expect(page).toHaveURL(nodeUrl);

    await page.goto('about:blank');
    await page.goto(nodeUrl);
    await expect(page.getByText(/1 hour \(2 hours written off, over estimate by 1 hour\)/)).toBeVisible();
  });
});
