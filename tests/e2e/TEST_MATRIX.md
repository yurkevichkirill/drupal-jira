# Task 9.2 test matrix — critical Project/Task journeys

This matrix was produced by turning the Block 1-8 acceptance criteria into a
candidate list of user-facing scenarios (an LLM-assisted pass over
`config/sync/*.yml`, the custom modules, and the existing task list/PRD
history), then manually cutting it down by business risk: duplicates of
Drupal core behavior, and anything that isn't actually reachable/meaningful
in this app's configuration, were removed rather than automated.

Only the "Covered by Playwright" rows are implemented in this task. Every
other row is either core/contrib behavior this app doesn't customize, or a
low-risk/manual item, and is listed so the decision is visible rather than
silently dropped.

## Covered by Playwright

| Journey | Test file |
|---|---|
| Kanban project creation; default type persists after reload | `project-management.admin.spec.ts` |
| Switching a project from Kanban to Scrum persists after reload | `project-management.admin.spec.ts` |
| Task creation with all principal fields (Project, Assignee, decimal estimate via Hours/Minutes, Body, default Backlog status); persists after reload | `task-management.user.spec.ts` |
| Omitting the required Project blocks task creation with a visible error | `task-management.user.spec.ts` |
| Editing a task persists a changed assignee, estimate, and description after reload | `task-management.user.spec.ts` |
| A newly created task shows in the right project/status board column and opens from its card | `task-board.user.spec.ts` |
| Regular user can drive backlog → in progress → review → reopen (their granted transitions) via real drag-and-drop; persists after reload | `task-board.user.spec.ts` |
| Regular user is denied the review → done ("approve") transition; card stays put after reload | `task-board.user.spec.ts` |
| Privileged/admin user can complete the full backlog → in progress → review → done happy path; persists after reload | `task-board.admin.spec.ts` |
| Attaching an image and a PDF document via Media Library; both remain visible after the task is re-opened | `media-attachments.admin.spec.ts` |
| Logging time via the custom TimeLog form updates the task's displayed remaining/written-off time; persists after reload and matches fixture math | `time-tracking.user.spec.ts` |
| Logging more than the estimate requires a reason (custom validation) and renders as an overrun after reload | `time-tracking.user.spec.ts` |

## Deliberately not covered by Playwright

| Scenario | Why it's out of scope here |
|---|---|
| Title/required-field validation on core node fields in general | Drupal core form API behavior, not this project's configuration or custom code. Covered indirectly by the Project-field validation test, which *is* project-specific (a required entity reference field wired up in `field.field.node.task.field_project.yml`). |
| Media Library's own upload/search/filter mechanics (file type restriction enforcement, "Apply filters", grid/table toggle, etc.) | Contrib module (`media_library`) internals; this project only configures which media types are allowed, which the attachment test exercises end-to-end. Re-testing the widget itself would be re-testing core, which the technical limitations explicitly rule out. |
| Content Moderation's generic transition engine (e.g. arbitrary custom workflow shapes, revision diffing, moderated_content view) | `content_moderation` contrib behavior. This suite only tests the *permission wiring* this project layered on top (`task_status_workflow`, `task_reviewer` role, and the board's own transition-gated drag-and-drop), which is the actual custom risk. |
| `views.view.tasks.yml` / `views.view.projects.yml` plain admin listing pages | Thin, unstyled default Views listings with no custom logic; low business risk, better caught by manual/visual review if they ever grow custom behavior. |
| `report_generator` module | No routes/forms are wired up yet (service scaffolding only at the time of this task); nothing user-facing exists to test. Revisit once it ships a UI. |
| `json_migration` / `update_tasks_projects` | One-off migration/update-hook modules, not part of any live user journey. |
| Field-level formatting edge cases of `duration_formatter` (e.g. singular/plural "hour"/"hours" boundary, negative-overrun wording) beyond the values the fixtures naturally exercise | Pure PHP string formatting with no DOM/permission interaction; a better fit for a PHPUnit unit test than an end-to-end browser test, per this task's brief ("do not re-test exhaustively with Playwright"). |
| Cross-browser/visual regression (non-Chromium engines, pixel-level styling) | Out of scope per `tests/e2e/README.md` (Chromium-only, functional assertions only). |

## Locator notes

All new specs use `getByRole`/`getByLabel`/`getByText` exclusively, with two
documented exceptions where no accessible alternative exists:

- **Kanban board drag-and-drop** (`helpers.dragTaskCardToColumn`): the board
  is driven by native HTML5 Drag and Drop against a `DataTransfer` object
  (`web/themes/custom/drupal_jira/js/task-board.js`), which Playwright's
  built-in `dragTo()` cannot reliably simulate. The helper dispatches real
  `DragEvent`s against the card/column elements, selected by their own
  stable `data-nid`/`data-status` attributes (not generated/DOM-depth
  selectors) — the only way to exercise the transition-permission check in
  `TaskStatusController` through the actual UI code path.
- **Entity reference autocomplete fields** (`helpers.pickAutocompleteSuggestion`):
  Project/Assignee use Drupal core's jQuery UI autocomplete widget, which
  renders suggestions with no ARIA role Playwright can target. The helper
  types the label and confirms the first live suggestion by keyboard
  (arrow-down + enter), mirroring real keyboard-driven usage instead of
  reaching into the suggestion list's markup.
