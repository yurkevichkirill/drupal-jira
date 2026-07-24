# DrupalJira

## Requirements

- Docker
- DDEV

## Project setup

Clone the repository:

```bash
git clone <repository-url>
cd drupal-jira
```

Start the environment:

```bash
ddev start
```

Install Composer dependencies (required on a fresh clone):

```bash
ddev composer install
```

Open the site:

https://drupal-jira.ddev.site

## Stop the environment

```bash
ddev stop
```

## Administrator account

Username: admin

Password: admin

## Xdebug

### Enable Xdebug

Enable Xdebug for the current project:

```bash
ddev xdebug on
ddev restart
```

Verify that Xdebug is enabled:

```bash
ddev xdebug status
```

Expected output:

```
xdebug enabled
```

### Disable Xdebug

Disable Xdebug when debugging is no longer required:

```bash
ddev xdebug off
ddev restart
```

Verify:

```bash
ddev xdebug status
```

Expected output:

```
xdebug disabled
```

Disabling Xdebug avoids unnecessary performance overhead and prevents PHP from attempting to connect to the IDE on every request.

## PHPStorm configuration

Configure a PHP server with the following settings:

| Setting                   | Value                   |
|---------------------------|-------------------------|
| Name                      | `drupal-jira`           |
| Host                      | `drupal-jira.ddev.site` |
| Port                      | `443`                   |
| Debugger                  | `Xdebug`                |
| Use path mappings         | Enabled                 |
| `/home/user/drupal-jira`  | `/var/www/html`         |

In **Settings → PHP → Debug**:

- Debug port: **9003**
- Enable **Can accept external connections**
- Start listening for PHP Debug Connections before starting a debugging session.

## Testing Web debugging

1. Enable Xdebug.
2. Start listening for debug connections in PHPStorm.
3. Set a breakpoint in any Drupal PHP file.
4. Open the site in the browser.
5. Execution should stop at the breakpoint.

## Testing CLI debugging (Drush)

1. Enable Xdebug.
2. Start listening for debug connections in PHPStorm.
3. Set a breakpoint in code executed by the Drush command.
4. Run:

```bash
ddev drush status
```

or

```bash
ddev drush cr
```

Execution should stop at the configured breakpoint.
