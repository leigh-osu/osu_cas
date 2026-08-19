# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Oregon State University College of Agricultural Sciences (CAS) Drupal 10 distribution** — a multisite platform hosting OSU agricultural sciences department sites. It runs on Acquia Cloud and is developed locally via DDEV.

## Working Model

The D7→D10 migration is finished. **There are no more site rebuilds and no more migration runs.** The site database is durable state, not a regenerable artifact.

Changes land one of two ways:

1. **Directly in the site** — content and configuration made through the UI or drush, captured with `drush cex` where it belongs in config.
2. **In the `osu_cas_multisite` modules** — `docroot/modules/custom/osu_cas_multisite` and its submodules (`osu_cas_multisite_degrees`, `osu_cas_multisite_editor`, `osu_cas_multisite_groups`, `osu_cas_weather`, `osu_live_feeds`).

Do not propose rebuilding, re-migrating, or re-importing content as a fix for a data problem — fix the data in place. Do not add new `migrate_plus.migration.*` configs or park config in `config_imports/` expecting something to replay it.

## Local Development Commands

All local development uses DDEV (PHP 8.3, Apache, MySQL 8.0):

```bash
ddev start                    # Start local environment
ddev stop                     # Stop environment
ddev ssh                      # Shell into web container
ddev xdebug                   # Toggle Xdebug on/off
```

### Drush via DDEV

```bash
ddev drush cr                 # Clear caches
ddev drush updb -y            # Run database updates
ddev drush cex -y             # Export config to config/
ddev drush cim -y             # Import full config
ddev drush uli                # Generate one-time login URL
ddev drush st                 # Status check
```

Note: `ddev drush` needs `--uri=https://osu-cas.ddev.site` to target the agsci site; any other URI resolves to `sites/default`.

### Utility Scripts

```bash
# Clean up stale permissions from roles, then export config
drush scr drush/scripts/clean_permissions.php && drush -y cex
```

### Creating a New Site

```bash
composer generate-site    # Interactive: prompts for production FQDN, creates sites/ dir and config/ dir
```

A brand-new site directory is then installed with:

```bash
ddev drush site:install osu_standard \
  --site-name="..." \
  --account-name="cws_dpla" \
  --sites-subdir="<fqdn>" \
  --yes
```

### Applying a Recipe

```bash
cd docroot && drush recipe ../recipes/osu_tours
cd docroot && drush recipe ../recipes/osu_trash
```

### Composer

```bash
composer install
composer update drupal/core-composer-scaffold drupal/core-recommended drupal/core-dev --with-all-dependencies
composer update vendor/package             # Update a specific package
composer outdated drupal/\*                # Check for Drupal package updates
```

## Architecture

### Multisite Structure

The codebase hosts multiple OSU department sites from a single Drupal installation using the **Domain module** (not Drupal core multisite). Sites share a single database but content is access-controlled by domain.

- `docroot/sites/agsci.oregonstate.edu/` — main site (College of Ag Sciences)
- `docroot/sites/landscapeplants.oregonstate.edu/` — secondary site
- `docroot/sites/sites.php` — maps hostnames to site directories
- `config/agsci.oregonstate.edu/` — config export for the main site (1400+ YAML files)

Per-domain settings live in config *collections* (`domain.<id>:system.site`), which override both global config and `domain.config.*` objects.

### Configuration Management

`config/` holds the full Drupal config export per site, used by `drush cim/cex`.

`config_imports/` is a leftover of the migration era (partial imports by type). Nothing replays it — leave it alone.

### Custom Modules (`docroot/modules/custom/`)

Custom modules are pulled via Composer from GitHub (osu-wams and leigh-osu orgs). Modules owned by the osu-wams org must be changed through entries in `patches/`, never edited in place; the leigh-osu packages are edited in the working copy and released through their own repos.

- **`osu_cas_multisite`** — primary home for project-specific behaviour (Layout Builder UX, account links, profile tab/listings) plus submodules for degrees, editor tooling, groups, weather, and live feeds
- **`osu_cas_site_sync`** — block linking the current page to its counterpart on production, with a side-by-side compare window
- **`osu_standard`** — installation profile used for fresh site installs
- **`osu_groups`** — group/organizational content using the Group module
- **`osu_publications`** — Publication content type (formerly Biblio)
- **`osu_editorial_workflow`** — content moderation configuration
- **`osu_paragraphs`** — paragraph type definitions
- **`osu_block_types`** / **`osu_custom_blocks`** — custom block content entities
- **`osu_profile`** — faculty/staff profile content type
- **`osu_bootstrap_layout_builder`** — Bootstrap grid integration for Layout Builder
- **`osu_migrations`** / **`osu_migrations_cas`** — historical D7→D10 migration definitions, retained for provenance; not run any more

Note: Group 2.3.2 keeps the entity id `group_content` but stores rows in `group_relationship_field_data` — SQL checks must use the relationship tables.

### Custom Themes (`docroot/themes/custom/`)

- **`manzanita`** — primary frontend theme (Bootstrap-based)
- **`madrone`** — secondary/alternate theme
- Admin theme: `gin` (contrib)

There is no build tooling for theme CSS; compile with `sass src/manzanita.scss css/manzanita.css`.

### Deployment (Acquia Cloud)

- `develop` branch → Pipelines builds `pipelines-build-develop` → Acquia **dev** environment (automatic)
- `main` branch → Pipelines builds `pipelines-build-main` → Acquia **stage** environment (automatic). Promote with `bash scripts-dev/promote_main.sh` — NOT a direct `git push origin develop:main`: `.ddev` is tracked on develop but deliberately kept off main, and the script commits develop's tree minus `.ddev` onto main (one synthetic commit per promote, no force pushes).
- **prod** tracks the `master` ref on Acquia's internal repo; production deploys are manual (Acquia Cloud UI)

Deployment hooks in `hooks/` run `drush updatedb` and `drush cache:rebuild` for all multisite domains in parallel after code deploy.

### Patches

`composer.json` applies patches stored in `patches/` to core and contrib modules. When updating packages, check that patches still apply cleanly.

### Drush Aliases

Remote Acquia environments accessible via:
- `drush @osucas.dev` — development
- `drush @osucas.stage` — staging
- `drush @osucas.prod` — production

## Legacy D7 Reference

The Drupal 7 source site remains available for lookups (what a page used to look like, what a field used to hold), not for re-importing:

- **D7 codebase**: `./legacy-d7/` (symlink to the original D7 site); custom modules under `legacy-d7/sites/all/modules/custom/`
- **D7 database**: the `$databases['migrate']` block in `docroot/sites/agsci.oregonstate.edu/settings.local.php` (untracked, per-environment)

## Reports and Scratch Output

Generated reports (CSV, Markdown, PDF) belong in `scripts-dev/` and stay untracked. Generator scripts themselves are fine to commit.
