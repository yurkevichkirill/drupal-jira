/**
 * Central place to read test configuration from environment variables.
 * Nothing here is a secret in itself for local dev (admin/admin is the
 * documented DDEV default) but credentials must never be hardcoded in
 * test files — they always flow through here.
 */
export const BASE_URL = process.env.BASE_URL || 'https://drupal-jira.ddev.site';

export const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
export const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'admin';

export const E2E_USER_USERNAME = process.env.E2E_USER_USERNAME || '';
export const E2E_USER_PASSWORD = process.env.E2E_USER_PASSWORD || '';

export const ADMIN_STORAGE_STATE = 'playwright/.auth/admin.json';
export const USER_STORAGE_STATE = 'playwright/.auth/user.json';
