# Repository Guidelines

## Project Structure & Module Organization

This is a Drupal 11 project based on `drupal/recommended-project`, with the web document root relocated to `web/`. Composer-managed dependencies live in `vendor/` and Drupal core is installed under `web/core`; do not edit either directly. Place custom Drupal modules in `web/modules/custom/`, custom themes in `web/themes/custom/`, and custom installation profiles in `web/profiles/custom/`. Contributed extensions belong in the matching `contrib/` directories and should be added through Composer when available. Site configuration and local settings are under `web/sites/`; DDEV-specific configuration is in `.ddev/`.

## Build, Test, and Development Commands

- `ddev start`: starts the local Docker/DDEV environment.
- `ddev composer install`: installs PHP dependencies from `composer.lock` after cloning or pulling dependency changes.
- `ddev drush status`: checks Drupal bootstrap and environment status.
- `ddev drush cr`: rebuilds Drupal caches after code, service, route, or configuration changes.
- `ddev stop`: stops the local environment.

Open the local site at `https://drupal-jira.ddev.site` after DDEV starts.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF line endings, final newline, trimmed trailing whitespace, and 2-space indentation for most files. `composer.json` and `composer.lock` use 4-space indentation. Use Drupal naming conventions for custom code: machine names in lowercase snake_case, module files prefixed with the module name, PHP classes in PSR-4 namespaces, and Twig templates named with Drupal theme hook patterns.

## Testing Guidelines

No project-specific test suite is currently defined. For new custom modules, add tests in the module’s `tests/` directory using Drupal’s PHPUnit-based test types where appropriate. Run Drupal checks from the DDEV container, for example `ddev phpunit` if PHPUnit is configured, and use `ddev drush cr` plus manual verification for behavior that depends on routing, plugins, or render cache.

## Commit & Pull Request Guidelines

Recent commits use short, imperative summaries such as `Updated README file` and `Initial Drupal 11 project setup with DDEV`. Keep commit subjects concise and focused on one change. Pull requests should describe the change, list verification steps, link related issues, and include screenshots for visible UI or theme changes. Note any database, configuration, or DDEV setup impacts explicitly.

## Security & Configuration Tips

Do not commit secrets, private keys, database dumps, or generated files from `web/sites/default/files/`. Keep environment-specific settings in DDEV or local settings files rather than hard-coding them in custom modules.
