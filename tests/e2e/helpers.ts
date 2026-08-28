import { expect, type Browser, type Locator, type Page } from '@playwright/test';
import { ADMIN_STORAGE_STATE } from './env';

/**
 * Builds a unique, human-legible fixture label for content created directly
 * by a spec (as opposed to the shared Drush seed). Follows the same
 * "E2E-<timestamp>-<random>" convention as tests/e2e/fixtures/seed.php, so
 * `npm run test:cleanup` sweeps up anything a test creates and forgets to
 * remove, and parallel/rerun tests never collide on titles.
 */
export function uniqueTitle(label: string): string {
  const stamp = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  return `E2E-${stamp} ${label}`;
}

/**
 * Creates a Project node through the real admin UI, in a throwaway admin
 * browser context, and returns its title and node id.
 *
 * Project creation has no dedicated permission in
 * config/sync/user.role.authenticated.yml, so only the admin account can do
 * it (see tests/e2e/README.md). Task-side specs that run as a regular user
 * still need a real project to select in the Project autocomplete field;
 * creating one via this helper keeps that fixture independent of any other
 * spec/test (it is not "an entity created by a previous test", it's a
 * dedicated setup step every consuming spec performs for itself).
 */
export async function createProjectAsAdmin(
  browser: Browser,
  titleSuffix: string,
): Promise<{ title: string; nid: number }> {
  const context = await browser.newContext({ storageState: ADMIN_STORAGE_STATE });
  const page = await context.newPage();
  const title = uniqueTitle(titleSuffix);

  await page.goto('/node/add/project');
  await page.getByLabel('Title', { exact: true }).fill(title);
  await page.getByRole('button', { name: 'Save' }).first().click();
  await expect(page).toHaveURL(/\/node\/\d+$/);
  const nid = Number(new URL(page.url()).pathname.split('/').pop());

  await context.close();
  return { title, nid };
}

/**
 * Selects an existing entity-reference-autocomplete target by typing its
 * label and confirming the first live suggestion, mirroring how a real user
 * drives the widget (JS-rendered jQuery UI autocomplete, no stable role to
 * target directly — see tests/e2e/README.md's locator conventions). Callers
 * must pass a label unique enough that only one suggestion can match.
 */
export async function pickAutocompleteSuggestion(page: Page, field: Locator, label: string): Promise<void> {
  await field.fill(label);
  // The jQuery UI autocomplete suggestion list is populated by an async
  // request; there is no stable role/label on the widget itself to await,
  // so give the request time to land before driving it by keyboard, the way
  // a human would after seeing the dropdown appear.
  await page.waitForTimeout(600);
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
}

/**
 * Attaches one already-uploaded-free file through the Media Library widget
 * of the currently open node form, walking the real "Add media" -> upload ->
 * save -> insert selected flow (no shortcuts through the media API).
 *
 * @param page - The page with a task add/edit form open.
 * @param tab - Which Media Library tab to use ('Document' is selected by
 *   default when the dialog opens).
 * @param filePath - Absolute path of the fixture file to upload.
 * @param altText - Alternative text; only required (and only filled) for
 *   the Image tab.
 */
export async function attachMedia(
  page: Page,
  tab: 'Document' | 'Image',
  filePath: string,
  altText?: string,
): Promise<void> {
  await page.getByRole('button', { name: 'Add media' }).click();
  const dialog = page.getByRole('dialog', { name: 'Add or select media' });

  if (tab === 'Image') {
    // The Library defaults to the Document tab; the tab links carry an
    // explicit role="button" and an accessibility name padded with
    // "Show "/" media" (see the widget's own markup), so match loosely.
    await dialog.getByRole('button', { name: /Image/ }).click();
    // The pane is swapped in over AJAX; wait for the Image tab's own upload
    // widget (distinct accepted extensions) so the file isn't handed to a
    // stale/hidden Document-tab input still present mid-swap.
    await dialog.getByText('Allowed types: png gif jpg jpeg webp.').waitFor();
  }

  await dialog.getByLabel('Add files').setInputFiles(filePath);
  // The "Remove" control only exists once the file has actually finished
  // uploading and the per-item fields (Name, and for images Alternative
  // text) have rendered — a more reliable readiness signal than a fixed wait.
  await dialog.getByRole('button', { name: 'Remove' }).waitFor();

  if (tab === 'Image' && altText) {
    await dialog.getByLabel('Alternative text').fill(altText);
  }

  await dialog.getByRole('button', { name: 'Save' }).click();
  await dialog.getByRole('button', { name: 'Insert selected' }).click();
  // Widget re-render after insertion is AJAX-driven; wait for the dialog to
  // actually close before doing anything else with the form.
  await dialog.waitFor({ state: 'hidden' });
}

/**
 * Drags a Kanban task card into another board column using the page's own
 * native HTML5 Drag and Drop handlers (see
 * web/themes/custom/drupal_jira/js/task-board.js), instead of calling the
 * status-update endpoint directly. This is the only path in the app that
 * enforces per-transition Content Moderation permissions (see
 * web/modules/custom/task_board/src/Controller/TaskStatusController.php),
 * so exercising it is required to test who is/isn't allowed to move a task.
 *
 * Playwright's built-in dragTo() does not reliably drive elements that rely
 * on a real DataTransfer object (as this board does), so the drag is
 * synthesized in-page with real DragEvent/DataTransfer instances dispatched
 * against the actual card/column elements — CSS selectors tied to the
 * board's own stable data-nid/data-status attributes, not generated
 * DOM-depth selectors, and documented here as the reason no
 * getByRole/getByLabel alternative exists for HTML5 DnD.
 *
 * @returns Whether the browser-side fetch to the status endpoint resolved
 *   with an ok (2xx) response.
 */
export async function dragTaskCardToColumn(
  page: Page,
  nid: number | string,
  targetStatus: string,
): Promise<boolean> {
  return page.evaluate(
    async ({ nid, targetStatus }) => {
      const card = document.querySelector<HTMLElement>(`.task-card[data-nid="${nid}"]`);
      const column = document.querySelector<HTMLElement>(`.task-board__column[data-status="${targetStatus}"]`);
      if (!card || !column) {
        throw new Error('Card or column not found on the board.');
      }

      const dataTransfer = new DataTransfer();
      const fire = (type: string, target: Element) =>
        target.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer }));

      fire('dragstart', card);
      fire('dragover', column);

      // The real handler kicks off an async fetch() from inside its 'drop'
      // listener; wait for that specific request/response pair instead of a
      // fixed timeout so the assertion after this resolves only once the
      // server has actually answered.
      const responsePromise = new Promise<boolean>((resolve) => {
        const originalFetch = window.fetch.bind(window);
        window.fetch = async (...args: Parameters<typeof fetch>) => {
          const response = await originalFetch(...args);
          const url = typeof args[0] === 'string' ? args[0] : (args[0] as Request).url;
          if (url.includes('/task-board/task/')) {
            window.fetch = originalFetch;
            resolve(response.ok);
          }
          return response;
        };
      });

      fire('drop', column);
      fire('dragend', card);

      const timeout = new Promise<boolean>((_, reject) =>
        window.setTimeout(() => reject(new Error('Timed out waiting for the status update request.')), 5000),
      );

      return Promise.race([responsePromise, timeout]);
    },
    { nid: String(nid), targetStatus },
  );
}
