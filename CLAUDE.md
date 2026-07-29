# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Environment

Everything runs inside DDEV (Docker). There is no host PHP workflow — prefix commands with `ddev`.

```bash
ddev start                 # boot containers; site at https://drupal-jira.ddev.site
ddev composer install      # required after clone / dependency changes
ddev drush cr              # rebuild caches after code, service, route or config changes
ddev drush status          # verify bootstrap
```

Stack: Drupal 11 (`drupal/recommended-project`), PHP 8.4, nginx-fpm, MariaDB 11.8, Drush 13. Admin login is `admin` / `admin`. Xdebug is enabled in `.ddev/config.yaml`; toggle with `ddev xdebug on|off` + `ddev restart` (see README for the PHPStorm mapping `/home/user/drupal-jira` → `/var/www/html`, port 9003).

## Code quality

```bash
ddev exec vendor/bin/phpcs              # Drupal + DrupalPractice standards
ddev exec vendor/bin/phpstan analyse    # level 1, phpstan-drupal extension
ddev exec vendor/bin/grumphp run        # both, as the pre-commit hook runs them
```

`composer lint` runs phpcs + phpstan together. Both tools are scoped to `web/modules/custom` and `web/themes/custom` only (`phpcs.xml.dist`, `phpstan.neon.dist`) — core and contrib are never analysed.

GrumPHP installs a `pre-commit` hook that shells back into the container (`EXEC_GRUMPHP_COMMAND: ddev exec php`), so commits fail on lint errors. `git commit --no-verify` bypasses it; only justified in emergencies.

No PHPUnit suite is configured. New custom modules should carry their own `tests/` directory using Drupal's PHPUnit test base classes.

## Configuration as code

This is the central workflow of the repo. Site configuration lives in `config/sync/` (set via `$settings['config_sync_directory'] = '../config/sync'`) and is committed. Structural changes are made through the admin UI, then exported:

```bash
ddev drush cex -y          # export active config to config/sync after UI changes
ddev drush cim -y          # import config/sync after pulling
ddev drush config:status   # should report no differences
```

Any commit that changes content types, fields, displays, views or site settings must include the corresponding `config/sync/*.yml` diff. Editing config on a shared environment without exporting is not allowed.

## Domain model

The app is a Jira-like tracker built entirely from core entities + config (installed with the `minimal` profile; `stark` theme; enabled modules include `node`, `field_ui`, `media`, `media_library`, `views`, `options`, `dblog`).

Two node types:

- **Project** (`node.type.project`) — `field_body`.
- **Task** (`node.type.task`) — `field_body`, `field_project` (required entity reference → Project), `field_assignee` (entity reference → user), `field_status` (list_string: `backlog`, `in_progress`, `review`, `done`; defaults to `backlog`), `field_estimate` (decimal 10,2), `field_attachments` (entity reference → media, bundles `image` + `document`, edited via the media library widget).

Media types `image` (`field_media_image`) and `document` (`field_media_file`) back attachments.

Because the model is config-only, feature work usually means: change in the UI → `drush cex` → commit YAML. Reach for a custom module in `web/modules/custom/` only when behaviour (plugins, services, hooks) is actually needed. `web/modules/custom/example` is a placeholder used to verify the lint pipeline.

## Layout

`web/` is the docroot. `web/core`, `web/modules/contrib`, `web/themes/contrib` and `vendor/` are Composer-managed and gitignored — never edit them. Custom code goes in `web/modules/custom/`, `web/themes/custom/`, `recipes/`; Composer installer paths are already wired for those locations.

Formatting follows `.editorconfig`: LF, UTF-8, final newline, 2-space indent (4 for `composer.json`/`composer.lock`).

## Feature workflow

Commits follow a PRD → task-list → implement flow (see the `ai-dev-tasks/` prompts, which are gitignored and may be absent locally; commit subjects like `Task 2.1: Add Project and Task content types` come from it). Keep commit subjects short and imperative, one change per commit.
