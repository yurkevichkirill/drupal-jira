# End-to-end tests (Playwright)

This directory holds the Playwright E2E suite for DrupalJira. Tests talk to a
running DDEV site over real HTTP; nothing here reads the database directly
inside a test assertion — direct/Drush access is reserved for the setup and
cleanup fixtures.

## Prerequisites

- DDEV running: `ddev start` (site at `https://drupal-jira.ddev.site`).
- Node.js 20+ and npm on the host (this tooling runs outside DDEV; it is
  plain JS/TS project tooling, not part of the PHP stack). Node 20 is
  required by the current `@playwright/test` release; the pinned version is
  recorded in `.nvmrc` — with `nvm` installed, run `nvm use` in the repo
  root to switch automatically. Without `nvm`, install Node 20+ from
  https://nodejs.org or your OS package manager.

## Install

```bash
npm ci                                        # install pinned dependencies
npx playwright install --with-deps chromium   # install the Chromium browser
```

`--with-deps` may prompt for `sudo` to install OS-level libraries. If you
don't have sudo/interactive access, drop the flag (`npx playwright install
chromium`) and install any missing OS packages yourself; the tests only
need one browser (Chromium) to stay fast.

## Configure

Copy the example env file and adjust if needed:

```bash
cp .env.example .env
```

`.env` is gitignored (see `.gitignore`); `.env.example` is committed and
holds only non-secret placeholders. Variables:

| Variable            | Purpose                                             | Default (if unset)                  |
|---------------------|------------------------------------------------------|--------------------------------------|
| `BASE_URL`           | Site root the tests run against                      | `https://drupal-jira.ddev.site`     |
| `ADMIN_USERNAME`     | Privileged/admin account for the `admin` project      | `admin`                              |
| `ADMIN_PASSWORD`     | Password for the above                                | `admin`                              |
| `E2E_USER_USERNAME`  | Plain authenticated (non-reviewer) test account       | *(empty — see below)*                |
| `E2E_USER_PASSWORD`  | Password for the above                                | *(empty — see below)*                |

`E2E_USER_USERNAME`/`E2E_USER_PASSWORD` must be set for the "regular user"
auth setup and `user`-project tests to run (they're skipped otherwise). The
seed script (below) provisions this account for you from the values in your
`.env`.

## Seed / cleanup test data

Setup and teardown use Drush directly (via `ddev drush scr`) against
Drupal's entity API — this is the sanctioned way to prepare/clean fixtures
without a custom write API; actual **test assertions** never touch Drush or
the DB, only HTTP via Playwright.

```bash
npm run test:seed      # creates a Project, a Task, and a TimeLog entry,
                        # tagged with a unique "E2E-<timestamp>-<random>"
                        # title prefix; also ensures the persistent
                        # E2E_USER_USERNAME/PASSWORD account exists
npm run test:cleanup   # deletes ANY node/media/time log whose label starts
                        # with "E2E-" — safe to run repeatedly, idempotent,
                        # never accumulates leftover data. The persistent
                        # E2E_USER_USERNAME account is NOT deleted by
                        # cleanup (it's long-lived, not per-run ephemeral)
```

Run `npm run test:seed` once before your first test run (it also creates the
regular test user), and `npm run test:cleanup` whenever you want a clean
slate (e.g. after a run, or before switching branches).

## Run tests

```bash
npm run test:e2e                          # full suite, all projects
npx playwright test --project=admin       # just the admin project
npx playwright test -g "smoke"            # tests matching a title/grep
npm run test:e2e:headed                   # headed browser
npm run test:e2e:debug                    # Playwright Inspector (step mode)
npm run test:e2e:ui                       # Playwright UI mode
```

## Viewing results after a run

```bash
npx playwright show-report                          # open the HTML report
npx playwright show-trace test-results/<test>/trace.zip   # open one trace
```

The HTML report path is printed at the end of every run. Traces, screenshots,
and video are all captured `retain-on-failure`/`only-on-failure`, so a
failing test always has a trace even on a single local run with no retries,
while a fully green run leaves no artifacts beyond a
`.last-run.json` marker — `test-results/`, `playwright-report/`, and
`playwright/.auth/` are all gitignored.

## How auth works

`tests/e2e/setup/auth.setup.ts` is a Playwright "setup project" (the
`setup` entry in `playwright.config.ts`) that logs in through the real
`/user/login` form for the admin account and, if configured, the regular
account, and persists each session as Playwright `storageState` under
`playwright/.auth/` (gitignored — these are live session cookies, not
committed). The `admin` and `user` projects declare `dependencies: ['setup']`
and load the corresponding storage state; the `anonymous` project uses an
empty storage state so those tests exercise a logged-out visitor.

## Critical journey coverage (Task 9.2)

Beyond the auth/permission smoke tests, `tests/e2e/TEST_MATRIX.md` documents
the full set of critical Project/Task journeys, which are covered and which
are deliberately left to Drupal core/manual review, and why:

- `project-management.admin.spec.ts` — Project creation/editing, Kanban → Scrum.
- `task-management.user.spec.ts` — Task creation/editing, required-Project validation.
- `task-board.user.spec.ts` / `task-board.admin.spec.ts` — Kanban board columns and
  Content Moderation transitions (drag-and-drop), including the permission
  boundary between a plain authenticated user and an admin/reviewer.
- `media-attachments.admin.spec.ts` — Media Library image + PDF attachments.
- `time-tracking.user.spec.ts` — Logging time and its effect on displayed estimates.

`tests/e2e/helpers.ts` holds the shared fixtures/utilities these specs use
(unique title generation, an admin-context Project fixture creator, the
entity-reference-autocomplete driver, the Media Library upload flow, and the
native HTML5 drag-and-drop simulator the Kanban board requires).

## Test data / domain notes

- **Project** / **Task** nodes, **Media** (image/document), and **TimeLog**
  entities (from the custom `time_tracking` module) are all created via the
  Drush seed script using Drupal's entity API.
- The `task_reviewer` role (`config/sync/user.role.task_reviewer.yml`) adds
  exactly two permissions on top of `authenticated`: `edit any task content`
  (which `authenticated` already has) and the workflow transition
  `use task_status_workflow transition approve`. The clearest
  black-box-visible distinction is admin-only pages like `/admin/people`
  (`administer users`), which is what `task-review.admin.spec.ts` /
  `task-review.user.spec.ts` assert on, since `admin` here is used as the
  stand-in privileged account.

## AI review of this setup

A short internal review of decisions made while building this suite, and
why each was accepted or rejected:

1. **Unique fixture keys.** Considered using node IDs as the uniqueness key
   for seeded content. Rejected — Drupal's serial node IDs aren't known
   before creation and aren't human-legible in test output. Accepted a
   `E2E-<timestamp>-<random>` title prefix instead, generated in
   `seed.mjs` and passed into the Drush script, so reruns/parallel runs
   never collide and failures are easy to spot by title alone.

2. **Secret handling.** Considered hardcoding `admin`/`admin` directly in
   spec files since it's a well-known DDEV default and "not really a
   secret." Rejected anyway — even non-sensitive credentials should flow
   through env/config, not be duplicated across test files, so all reads
   go through `tests/e2e/env.ts`, sourced from `.env` (gitignored) with
   `.env.example` committed as a placeholder-only template.

3. **Direct DB access from tests.** Considered writing seed data directly
   via SQL for speed. Rejected in favor of Drupal's entity API
   (`Node::create`, `User::create`, the `time_log` storage) via
   `drush scr`, so seeded content still goes through validation, field
   defaults, and hooks the same way real content would — closer to
   production behavior and less likely to silently create invalid data
   that later diverges from what real users can create.

4. **Auth strategy.** Considered logging in via HTTP request context
   (`APIRequestContext`) hitting the login form directly with `fetch`,
   bypassing the browser. Rejected — Drupal's login form doesn't expose a
   trivial CSRF-free POST target and the browser-based Playwright "setup
   project" + `storageState` pattern is the documented, supported approach
   and keeps auth exercised through the real UI once, then reused cheaply.

5. **"Log out" link as post-login proof.** Initially asserted a `Log out`
   link becomes visible after login. Rejected after running the suite for
   real — the `stark`-based theme here doesn't render a persistent account
   menu, so that locator never appears even on a successful login.
   Replaced with asserting the actual Drupal post-login redirect URL
   (`/user/{uid}`), which is theme-independent and was verified against
   the live site.

6. **Distinguishing reviewer vs. regular user.** `task_reviewer` and
   `authenticated` differ only by a workflow transition permission
   (`approve`) that isn't easily observable without also creating and
   moving a task through moderation states in every test run. Accepted a
   pragmatic proxy instead: use the `admin` account (superuser, so it
   trivially has `approve`) against an unambiguous admin-only page
   (`/admin/people`, gated by `administer users`) versus the regular user
   getting a 403. Documented this tradeoff here rather than hiding it.

7. **Flakiness from fixed waits.** Considered `page.waitForTimeout()` after
   login/navigation to "let things settle." Rejected outright — every
   assertion in this suite uses Playwright's auto-waiting
   (`toBeVisible()`, `toHaveURL()`, response status checks), which proved
   sufficient in practice — no timeouts are needed; the whole suite runs in
   under 5 seconds locally.

8. **Cleanup safety.** Considered cleaning up only nodes created by the
   current run (tracked by ID). Rejected in favor of a broader
   `title LIKE 'E2E-%'` / `name LIKE 'E2E-%'` sweep across nodes, media,
   users, and `time_log` entities, so a crashed run that never reached its
   own cleanup step doesn't leak data forever — the next `test:cleanup`
   (or next `test:seed`, if wired into CI as a pre-step) sweeps it up
   regardless of which run created it.
