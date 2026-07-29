# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the Drupal Sitewide Alert module, a contrib module for Drupal 10.3+ and 11. It provides the ability to display alert messages at the top of all pages on a Drupal site.

### Key Features
- Multiple alerts can be displayed simultaneously
- Configurable alert styles (key|value pairs defined at `/admin/config/sitewide_alerts`)
- Dismissible alerts (browser-based, no login required)
- Scheduling with start/end times (scheduled alerts must also be marked "Active")
- Page visibility rules with wildcard support
- Fieldable entity - additional fields can be added via Field UI
- Cache-friendly design - alerts load asynchronously to preserve page caching

## Development Environment

This project uses [ddev-drupal-contrib](https://github.com/ddev/ddev-drupal-contrib) for local development, which provides a ready-made DDEV setup for Drupal contrib module development.

- PHP 8.3, MariaDB 10.11, nginx
- Drupal webroot is at `web/`

Common DDEV commands:
```bash
ddev start                    # Start the environment
ddev drush <command>          # Run Drush commands
ddev ssh                      # SSH into the web container
```

## Code Quality Commands

```bash
# PHP CodeSniffer (Drupal coding standards)
ddev exec ./vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,css,info,txt,md,yml src/ tests/ modules/ sitewide_alert.module

# PHPStan (static analysis)
ddev exec ./vendor/bin/phpstan analyse
```

## Running Tests

Tests are located in `tests/src/` and use Drupal's testing framework:

```bash
# Run all module tests via DDEV
ddev exec phpunit -c web/core/phpunit.xml.dist web/modules/custom/sitewide_alert/tests/

# Run a specific test class
ddev exec phpunit -c web/core/phpunit.xml.dist --filter SitewideAlertTest

# Run kernel tests only
ddev exec phpunit -c web/core/phpunit.xml.dist web/modules/custom/sitewide_alert/tests/src/Kernel/

# Run functional tests only
ddev exec phpunit -c web/core/phpunit.xml.dist web/modules/custom/sitewide_alert/tests/src/Functional/
```

## Architecture

### Entity Structure
- **SitewideAlert** (`src/Entity/SitewideAlert.php`): The main content entity with support for revisions, translations, and editorial workflows. Key fields: name, style, message, dismissible, scheduled_alert, scheduled_date, limit_to_pages.

### Key Services
- **SitewideAlertManager** (`src/SitewideAlertManager.php`): Central service for querying active/visible alerts and calculating next scheduled changes
- **SitewideAlertRenderer** (`src/SitewideAlertRenderer.php`): Builds the render array for displaying alerts. Supports both client-side (default) and server-side rendering modes
- **CliCommands** (`src/CliCommands.php`): Shared CLI implementation for create/delete/enable/disable operations. The Drush commands in `src/Drush/Commands/` are a thin wrapper around this service

### Submodules
- **sitewide_alert_block** (`modules/sitewide_alert_block/`): Optional module to render alerts in a block instead of at page top
- **sitewide_alert_domain** (`modules/sitewide_alert_domain/`): Experimental integration with the Domain module for multi-site setups

### Alert Display Flow
1. `sitewide_alert_page_top()` hook adds alerts to page top (unless block submodule is enabled)
2. `SitewideAlertRenderer::build()` creates the render array
3. **Client-side mode (default):** JavaScript in `js/init.js` fetches alerts via `/sitewide_alert/load` endpoint after page load, preserving page caching
4. **Server-side mode (experimental):** Alerts are rendered inline in the HTML response, reducing layout shift but making page cache dependent on alert content. Adds `url.path` and `languages:language_interface` cache contexts

### Validation Constraints
- `ScheduledDateProvided`: Ensures scheduled alerts have valid date ranges
- `LimitToPages`: Validates page path restrictions

## Admin Routes
- `/admin/content/sitewide_alert` - Alert management listing
- `/admin/content/sitewide_alert/add` - Create new alert
- `/admin/config/sitewide_alerts` - Global settings (alert styles, admin page visibility)

## Drush Commands
The module provides Drush commands in `src/Drush/Commands/SitewideAlertCommands.php` for managing alerts via CLI.

## Permissions

There are two similarly-named admin permissions -- be careful not to mix them up:
- `administer sitewide alert` -- access to the config form at `/admin/config/sitewide_alerts`
- `administer sitewide alert entities` -- entity-level admin permission defined on the entity type annotation. Note: the custom `SitewideAlertAccessControlHandler` does not delegate to the parent class, so this permission only grants access through Drupal's entity admin routes, not through `checkAccess()`/`checkCreateAccess()` directly

## Gotchas

- **Scheduling requires Active:** An alert must have `status=1` (Active) AND `scheduled_alert=TRUE` with valid dates to appear on schedule. Scheduled but inactive alerts are never shown.
- **Schedule boundary behavior:** `isScheduledToShowAt()` treats start time as inclusive (`>=`) and end time as exclusive (`<`)
- **preSave clears dates:** When `scheduled_alert` is set to FALSE, `preSave()` nulls out the `scheduled_date` field values
- **DDEV symlinks:** The module source lives at the repo root. DDEV symlinks it into `web/modules/custom/sitewide_alert/` so Drupal can discover it. Test paths in phpunit commands use the `web/modules/custom/` prefix.

## Theming

### CSS Classes
Alerts receive a wrapper with classes: `sitewide-alert`, `alert`, and `alert-{STYLE_KEY}` (e.g., `alert-default`).

### Template Suggestions
Override `sitewide-alert.html.twig` with these suggestions:
- `sitewide-alert--{STYLETYPE}.html.twig`
- `sitewide-alert--dismissible.html.twig`
- `sitewide-alert--notdismissible.html.twig`
- `sitewide-alert--{STYLETYPE}--dismissible.html.twig`
- `sitewide-alert--{STYLETYPE}--notdismissible.html.twig`