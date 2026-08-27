import { expect, test as setup } from '@playwright/test';
import {
  ADMIN_PASSWORD,
  ADMIN_STORAGE_STATE,
  ADMIN_USERNAME,
  E2E_USER_PASSWORD,
  E2E_USER_USERNAME,
  USER_STORAGE_STATE,
} from '../env';

/**
 * Logs in through the real Drupal login form at /user/login and persists
 * the resulting session as Playwright storageState. Runs once per
 * "setup" project before the dependent test projects (see
 * playwright.config.ts), following the modern Playwright auth pattern.
 */
async function loginAndSave(
  page: import('@playwright/test').Page,
  username: string,
  password: string,
  storageStatePath: string,
) {
  await page.goto('/user/login');
  await page.getByLabel('Username', { exact: true }).fill(username);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Log in' }).click();
  // The theme doesn't render a persistent "Log out" link, so assert on
  // the redirect Drupal performs after a successful login instead: it
  // lands on the user's own profile page at /user/{uid}.
  await expect(page).toHaveURL(/\/user\/\d+/);
  await page.context().storageState({ path: storageStatePath });
}

setup('authenticate as admin', async ({ page }) => {
  await loginAndSave(page, ADMIN_USERNAME, ADMIN_PASSWORD, ADMIN_STORAGE_STATE);
});

setup('authenticate as regular user', async ({ page }) => {
  setup.skip(
    !E2E_USER_USERNAME || !E2E_USER_PASSWORD,
    'E2E_USER_USERNAME/E2E_USER_PASSWORD not set. Run "npm run test:seed" first, ' +
      'or set them in .env for a pre-existing account.',
  );
  await loginAndSave(page, E2E_USER_USERNAME, E2E_USER_PASSWORD, USER_STORAGE_STATE);
});
