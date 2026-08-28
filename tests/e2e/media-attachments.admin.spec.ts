import path from 'node:path';
import { expect, test } from '@playwright/test';
import { attachMedia, pickAutocompleteSuggestion, uniqueTitle } from './helpers';

const FIXTURES_DIR = path.join(__dirname, 'fixtures', 'files');
const IMAGE_PATH = path.join(FIXTURES_DIR, 'e2e-attachment.png');
const PDF_PATH = path.join(FIXTURES_DIR, 'e2e-attachment.pdf');

/**
 * Runs under the "admin" project. No role in config/sync/user.role.*.yml
 * grants 'create media' for either the image or document media type (only
 * 'view media' is granted to authenticated/anonymous), so uploading through
 * the Media Library widget is only reachable by the superuser.
 */
test.describe('Media Library attachments on a Task', () => {
  test('an image and a PDF document can be attached and remain visible after the task is re-opened', async ({
    page,
  }) => {
    const projectTitle = uniqueTitle('Media Project');
    await page.goto('/node/add/project');
    await page.getByLabel('Title', { exact: true }).fill(projectTitle);
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);

    const taskTitle = uniqueTitle('Media Task');
    const imageAlt = `${taskTitle} image`;

    await page.goto('/node/add/task');
    await page.getByLabel('Title', { exact: true }).fill(taskTitle);
    await pickAutocompleteSuggestion(page, page.getByLabel('Project', { exact: true }), projectTitle);

    await attachMedia(page, 'Document', PDF_PATH);
    await expect(page.getByText('e2e-attachment.pdf')).toBeVisible();

    await attachMedia(page, 'Image', IMAGE_PATH, imageAlt);
    await expect(page.getByRole('img', { name: imageAlt })).toBeVisible();

    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page).toHaveURL(/\/node\/\d+$/);
    const nodeUrl = page.url();

    await expect(page.getByText('e2e-attachment.pdf')).toBeVisible();
    await expect(page.getByRole('img', { name: imageAlt })).toBeVisible();

    // Re-open the task from a fresh navigation: both attachments must still
    // be there, not just present in the response that followed the save.
    await page.goto('about:blank');
    await page.goto(nodeUrl);
    await expect(page.getByText('e2e-attachment.pdf')).toBeVisible();
    await expect(page.getByRole('img', { name: imageAlt })).toBeVisible();
  });
});
