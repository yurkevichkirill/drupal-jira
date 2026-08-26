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
