# Figma MCP: Project Dashboard reference

Notes from mapping the Figma "Jira Clone (Community)" Project Dashboard screen
against the current `drupal_jira` theme and the `task_board` View, using the
Figma MCP server.

## Setup

- **MCP client:** Figma's official Dev Mode MCP server, reached through Claude
  Code's built-in `figma` MCP connection (tools prefixed `mcp__figma__*`,
  e.g. `get_design_context`, `get_metadata`, `get_screenshot`, `whoami`).
- **Connection steps (no secrets involved):**
  1. Run `/mcp` inside Claude Code.
  2. Approve the browser-based OAuth sign-in against the Figma account that
     should be used (no API token is generated, stored, or committed).
  3. Confirm the session with `whoami` — it returns the connected handle,
     email, and plan/seat, which is how the access issue below was diagnosed.
- **Access note:** the MCP session only has a **View** seat, and Figma's Dev
  Mode MCP tools require **editor** access to a file. The original community
  file (`rKpV7WKzbwb7y1xiaYg1re`) could not be read for that reason. Fix used:
  duplicate the file into the account's own drafts ("Duplicate to your
  drafts" in the Figma file menu), which grants owner/editor rights on the
  copy. All calls below ran against that duplicate.

## Selected node

- File key: `1ZjOH1DDrnmBxCd42yJwys` ("Jira Clone (Community) — Copy").
- The link the user provided (`node-id=24-14931`) resolves to the file's
  **Cover** page (marketing hero art), not a dashboard screen.
- The actual assembled Project Dashboard lives on the `⬛ Onboarding` page,
  frame **`9:1447` "Home"** (1440×1024) — Navbar + Sidebar + project Header/
  Toolbar + Kanban Board + Quickstart panel. This is the node the mapping
  below is based on. Supporting component instances (`Sidebar` `9:2004`,
  `Navbar` `9:1835`, `Kanban Card` `10:1106`) were cross-checked from the
  `🔶 Components` page.

## Mapping: Figma Project Dashboard → Drupal

| Area | Figma | Drupal today | Notes |
|---|---|---|---|
| Dashboard shell & navigation | `Navbar` (`9:1835`): logo, Your work/Projects/Filters/Dashboards/Teams/Plans/Apps, Create button, trial banner, search, bell/help/settings, avatar. `Sidebar` (`9:2004`): Projects panel, recent project, "View all projects". | Not implemented. `drupal_jira` theme has no page/navbar/sidebar template — only the two views templates and the paragraph preprocess hooks. Drupal's own admin toolbar is the only chrome present. | Biggest gap: there's no themed shell to map onto; would need new template(s)/regions if this is ever built. |
| Page title & project actions | `Header` (`9:2686`): project icon + project name, "Project settings" button. `Toolbar` (`9:2684`): Summary / **Board** / List / Calendar / Timeline / Approvals / Forms / Pages / Attachments tabs. | `views-view--task-board.html.twig` only prints the View's static `{{ title }}` ("Task board"). No project entity (name/icon) is rendered, no settings action, no view-mode tabs. | The `board/%` argument (`field_project_target_id`) already resolves a Project node server-side — the twig template just never surfaces it. |
| Search / filter controls | Board header: "Search board" input, assignee avatar facepile, Share / Filter / **Group by: Status** / More buttons. | View has `exposed_form: basic` configured but no fields are actually exposed (`filters` only has fixed `status`/`type`). No search box, no facepile, no Filter/Share/More controls render. | Grouping by status is already the fixed behavior (via the style plugin), just not user-toggleable. |
| Status columns | `TO DO` / `CONCEPTING` / `DESIGN` columns, each a pill title + numeric count + `+ Create` affordance. | `board_columns` built in `drupal_jira_preprocess_views_view()` from `field_status` allowed values (`backlog`, `in_progress`, `review`, `done`), rendered as `.task-board__column` with `.task-board__column-title` + `.task-board__count`. | Structural match is solid (fixed columns even when empty, live count). Two intentional differences: Drupal keeps its real domain status labels rather than the demo's TO DO/CONCEPTING/DESIGN, and there's no per-column "+ Create" button in the current markup. |
| Task cards & live fields | `Kanban Card` (`10:1106`): colored status pill, numeric id, title, small "Create" meta line, drag icon. | `node--task--teaser.html.twig`: draggable `<article data-nid>`, title → AJAX modal (task Full view mode), body from a `task_card` variable populated elsewhere (`project_statistics_preprocess_node()`, not this theme) — CSS (`.task-card__meta--mine`, `.task-card__mine-badge`, `.task-card__initials`, `.task-card__list/item`) implies it surfaces `field_assignee` (with a "mine" highlight + initials), and likely `field_estimate`. | Card-level status pill and any "id"/estimate badge from Figma aren't reproduced — status is only conveyed by column placement in Drupal. |
| Colors / typography / spacing / borders / icons | CSS vars from the design: `--primary/1000 #0a65e4`, `--primary/200 #cee0fa`, `--secondary/700…1000` (`#5b5772`→`#140f36`), `--grey-scale/50…900` (`#f5f5f5`→`#202020`), page bg `--sky-blue/200 #f2faff`; font **Outfit** (Regular/Medium/SemiBold, 12–20px); radius tokens 4/5/6/8/50px; gap/padding tokens 6/8/10/12/14/16/20/30/40px; 1–2px strokes; SVG icon set (search, bell, settings, board/list/calendar/timeline, etc). | `task-board.css` uses ad-hoc hex values (`#d3d8de`, `#f3f4f7`, `#4a90d9`, `#6b7280`, `#1d4ed8`, `#e5e7eb`) with no shared token layer, and the default system font stack — `Outfit` isn't loaded. No icon set; the board relies on plain text/emoji-free labels. | Adopting the design would mean introducing a token layer (CSS custom properties or a Sass map) and loading the `Outfit` webfont — neither exists in the theme today. |

## Intentional differences (if implementing this design)

- Keep Drupal's real `field_status` allowed values (backlog/in_progress/
  review/done) instead of the demo's TO DO/CONCEPTING/DESIGN labels — the
  column labels are config-driven domain data, not copy to hardcode.
- No navbar/sidebar/project-header chrome exists yet; scope for that is
  bigger than the board itself and wasn't inferred from the board node alone.
- Exposed filters and search would need new View config (`config/sync/
  views.view.task_board.yml` diff), not just template/CSS changes.

## Task 8.3: Drupal best-practice review

Final review of the completed board (drag-and-drop status changes, live
column/pill/empty-state updates, project stats sync) against Drupal theming
and coding conventions. Two passes were used: the `/code-review` AI agent
(twice, at `medium` and `high` effort) against the working-tree diff, plus
manual verification against the running site — a real logged-in session over
HTTP, not just reading the code, since no browser tool was available this
session. Every recommendation below was checked against actual request/
response behavior or `web/core` source before being accepted or rejected.

### Comparison with Figma node `24:14931`

`node-id=24-14931` still resolves to the file's Cover/marketing page (see
"Selected node" above), not a dashboard screen — this was re-checked, not
just carried over from the earlier mapping session. The comparison basis
remains frame `9:1447` "Home" and the `Kanban Card` (`10:1106`) component.
Nothing in this task's board work (drag-and-drop, AJAX stats sync, a11y
fixes) closed the structural gaps already logged in the mapping table above
(no navbar/sidebar, no per-card id/estimate badge, no design token layer) —
those remain intentional, out-of-scope differences for the reasons already
given there. The one visual addition from this task, the exposed "Search
board" title filter, matches the Figma header's search affordance in intent,
though not styling (still the theme's default exposed-form markup).

### AI findings: accepted and fixed

1. **Access control bypass on Content Moderation transitions (found by
   manual review, not the AI pass).** `TaskStatusController::update()` wrote
   `field_status` directly and only checked generic `node.update` access.
   But `workflows.workflow.task_status_workflow.yml` gates each state change
   behind a named transition permission (e.g. `task_reviewer` alone holds
   `use task_status_workflow transition approve`, the only path from
   `review` to `done`), and the controller never set `moderation_state` at
   all — silently desyncing it from `field_status` on every drag. Any
   authenticated user (all of whom hold generic `edit any task content`)
   could drag a card straight to Done, bypassing the review gate entirely.
   **Fixed**: the controller now resolves valid transitions for the current
   user via the core `content_moderation.state_transition_validation`
   service (`StateTransitionValidation::getValidTransitions()`), rejects the
   request with 403 when the target state isn't one of them, and — only once
   permitted — writes `moderation_state` alongside `field_status`. Verified
   live: a plain authenticated user got `403` moving a task from `review` to
   `done`; granting `task_reviewer` and stepping through the real transition
   chain (`in_progress` → `review` → `done`) returned `200` at each step, and
   `field_status`/`moderation_state` were confirmed equal in the database
   afterwards. This is server-side and un-bypassable by hiding UI — exactly
   per the "access control must remain server-side" requirement.
2. **Status pill on the moved card used the wrong CSS class after a drop**
   (`task-board.js`). `refreshCardStatus()` built the modifier class from
   `column.dataset.status` (the raw field value, e.g. `in_progress`), but
   the CSS/Twig both derive it through the `clean_class` filter
   (`in-progress`, dash). The card's pill silently fell back to the default
   grey color after every move. Fixed by reading the modifier suffix off the
   column's own already-`clean_class`'d pill element instead of
   reconstructing it, so the two can never drift apart.
3. **Duplicated remaining-estimate/tasks-summary formatting** between
   `project_statistics.module` and the new `TaskStatusController`. Moved
   both into the services that already own the underlying data:
   `DurationFormatter::formatRemaining()` and
   `TaskStatService::formatTasksSummary()`. `project_statistics.module`'s
   private `_project_statistics_format_remaining()` helper was deleted; both
   the block/task-card preprocessing and the AJAX controller now call the
   same two methods, so the numbers can't read differently between a full
   page load and a drag-and-drop update.
4. **`.task-card__status-pill`'s base rule duplicated
   `.task-board__column-pill`'s** property-for-property instead of sharing a
   selector list, unlike the color-variant rules right below it, which
   already combine the two. Fixed by combining the base rule too.
5. **Missing declared module dependencies.** `task_board` calls
   `drupaljira.task_stat`/`drupaljira.duration_formatter`/
   `content_moderation.*` services without declaring the owning modules;
   `project_statistics` had the same gap for `duration_formatter`. Both
   `.info.yml` files now list every module whose services they consume.

### AI findings: reviewed and deferred (not fixed)

- **N+1 query in `TaskStatService::getProjectStats()`, now also hit on every
  drag-and-drop move, not just full page loads.** Confirmed: `getLoggedHours()`
  issues one `time_log` query per task, called once per task in the project.
  Not fixed here — the service's existing architecture already paid this
  cost on every board page load before this task; running it once more per
  drag (a low-frequency, single-user interaction, not a page-view-scale
  operation) is a real but low-severity regression. A proper fix means
  batching `TaskStatService`'s per-task queries, which is a service-level
  change well beyond this task's board-focused scope and risks touching
  code paths (the stats block, task cards) not otherwise part of this
  review. Left as a follow-up.

### Manually reviewed areas (checklist)

- **Theme hooks / template suggestions.** `drupaljira_project_stats` and
  `drupaljira_task_card` are declared via `hook_theme()` with an explicit
  variable contract; no business logic lives in the `.html.twig` files
  themselves — all data is prepared in `hook_preprocess_HOOK()`
  implementations in `project_statistics.module`. Confirmed no raw
  `#markup`/`|raw` output anywhere in the touched templates; every value
  goes through Twig's default auto-escaping.
- **Render arrays, attributes, cacheability.** Traced how
  `drupal_jira_preprocess_views_view()`'s `board_project` cache-tag merge
  actually reaches the final render array by reading `web/core`'s
  `ThemeManager::render()` and `Renderer::doRender()`: a preprocess hook's
  `$variables['#cache']` is the only officially-bubbled key (core comment:
  *"This is the officially supported method of attaching bubbleable
  metadata from preprocess functions"*) — writing to
  `$variables['view']->element['#cache']` instead (the original code) skips
  that path. This was already corrected in an earlier pass of this task by
  switching to `$variables['#cache']`. By contrast, `task_board.module`'s
  `hook_views_pre_render()` mutating `$view->element['#cache']['contexts']`
  directly **is** correct: traced through `ViewExecutable::render()` and
  `DisplayPluginBase::render()` (`'#cache' => &$this->view->element['#cache']`)
  and confirmed it's a live reference by the time the hook runs, which is
  the standard Views API pattern for this hook. No change needed there.
- **Library declarations / asset scope.** `task_board` (CSS+JS) and its
  `task_board_font` dependency are only attached from
  `views-view--task-board.html.twig` via `attach_library()`, not globally.
  `core/once` and `core/drupal.ajax`/`drupal.dialog.ajax` are declared as
  real dependencies rather than assumed present.
- **Drupal behaviors / `once()`.** `task-board.js` registers everything
  under `Drupal.behaviors.taskBoard.attach(context)` and scopes every
  listener with `once('task-board-card'|'task-board-column', selector,
  context)`, so re-attachment after the task-edit AJAX modal closes (which
  re-triggers `Drupal.attachBehaviors()`) won't double-bind handlers.
- **Escaping / safe Twig output.** No unsafe output found (see theme hooks
  bullet above); translated strings use `t()`/`|t` with placeholders, not
  string concatenation.
- **Semantic HTML / keyboard focus / accessible names.** Fixed two real
  issues: `.task-board__search label` and `.task-card__label` (the
  Assignee/Remaining `<dt>` terms) were hidden with `display: none`, which
  removes them from the accessibility tree entirely — not just visually —
  leaving the search field and the card's definition list without
  accessible names/labels for screen readers. Replaced both with the
  standard clip-based visually-hidden technique. Also added
  `aria-current="page"` to the active "Board" toolbar tab so the current
  view is conveyed as text/state, not by color alone. No `outline: none` or
  other focus-ring suppression exists anywhere in `task-board.css` —
  keyboard focus rings are the browser default throughout.
  **Known, accepted gap**: the drag-and-drop status change itself has no
  keyboard equivalent (native HTML5 DnD is pointer-only). Not fixed in this
  task — building an accessible alternative (e.g. a per-card status control)
  is new UI surface beyond a "fix what's broken" review, and a keyboard user
  already has a working path today: the task's own edit form (reachable via
  the card's title link, which is a normal focusable, keyboard-activatable
  `<a>`) can change `field_status`/`moderation_state` under the same
  transition permissions enforced above. Flagging as a follow-up rather than
  silently accepting the limitation.
- **View filtering, route/entity access, workflow permissions.** The
  `task_board.update_status` route requires both `_csrf_request_header_token`
  and `_entity_access: node.update`, now layered under the transition check
  above. The `task_board` View's page display requires `access content` and
  filters to `type = task`, `status = published`; the `board/%` contextual
  filter validates the argument against the `project` bundle. Verified with
  real HTTP requests (not just reading config) as described in finding 1.

### Verification run

- `ddev exec vendor/bin/phpcs web/modules/custom/task_board
  web/modules/custom/project_statistics web/modules/custom/task_stat
  web/modules/custom/duration_formatter` — 0 errors, 0 warnings.
- `ddev exec vendor/bin/phpstan analyse` on the same paths — no errors.
- `ddev drush cr` after every code change in this task.
- Live HTTP verification (via `curl` against the running `ddev` site with
  real session cookies, admin and non-privileged users) of: the AJAX status
  endpoint's JSON shape, the 403/200 transition-permission behavior above,
  `field_status`/`moderation_state` staying in sync after a save, and that
  the fixed JS (`findRow`, `refreshEmptyState`, `refreshCardStatus`,
  `modifierClass`) is actually present in the aggregated asset Drupal serves
  (not a stale cache) — since no browser tool was available to click through
  the UI directly this session.
