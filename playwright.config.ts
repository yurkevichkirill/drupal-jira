import { defineConfig, devices } from '@playwright/test';
import { config as loadEnv } from 'dotenv';
import { ADMIN_STORAGE_STATE, BASE_URL, USER_STORAGE_STATE } from './tests/e2e/env';

loadEnv();

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: /.*\.spec\.ts/,
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 2 : undefined,
  reporter: [['html', { open: 'never' }], ['list']],
  outputDir: 'test-results',

  use: {
    baseURL: BASE_URL,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'setup',
      testDir: './tests/e2e/setup',
      testMatch: /.*\.setup\.ts/,
    },
    {
      name: 'admin',
      use: {
        ...devices['Desktop Chrome'],
        storageState: ADMIN_STORAGE_STATE,
      },
      testMatch: /.*\.admin\.spec\.ts/,
      dependencies: ['setup'],
    },
    {
      name: 'user',
      use: {
        ...devices['Desktop Chrome'],
        storageState: USER_STORAGE_STATE,
      },
      testMatch: /.*\.user\.spec\.ts/,
      dependencies: ['setup'],
    },
    {
      name: 'anonymous',
      use: {
        ...devices['Desktop Chrome'],
        storageState: { cookies: [], origins: [] },
      },
      testMatch: /.*\.(anon|spec)\.ts/,
      testIgnore: /.*\.(admin|user)\.spec\.ts/,
    },
  ],
});
