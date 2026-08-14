# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Oregon State University College of Agricultural Sciences (CAS) Drupal 10 distribution** — a multisite platform for migrating and hosting OSU agricultural sciences department sites from Drupal 7. It runs on Acquia Cloud and is developed locally via DDEV.

## Local Development Commands

All local development uses DDEV (PHP 8.3, Apache, MySQL 8.0):

```bash
ddev start                    # Start local environment
ddev stop                     # Stop environment
ddev stop -O -R               # Stop and remove database (full reset)
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

### Partial Config Import (used during migrations)

```bash
ddev drush config:import --partial --source=../config_imports/storage -y
ddev drush config:import --partial --source=../config_imports/content_type -y
ddev drush config:import --partial --source=../config_imports/fields -y
ddev drush config:import --partial --source=../config_imports/display -y
ddev drush config:import --partial --source=../config_imports -y
```

### Migrations

```bash
ddev drush ms                               # Migration status
ddev drush ms --tag='OSU Media'             # Status for a migration group
ddev drush migrate:import --tag='OSU Accounts'
ddev drush migrate:import --tag='OSU Media'
ddev drush migrate:import --tag='OSU Taxonomy'
ddev drush migrate:import d7_domain
ddev drush migrate:import upgrade_d7_biblio_publication --force
```

### DDEV Snapshots (for migration checkpointing)

```bash
ddev snapshot -n aftermedia               # Save a named snapshot
ddev snapshot restore aftermedia          # Restore to named snapshot
```

### Site Installation

```bash
ddev drush site:install osu_standard \
  --site-name="College of Agricultural Sciences" \
  --account-name="cws_dpla" \
  --account-pass="ok" \
  --sites-subdir="agsci.oregonstate.edu" \
  --yes
```

### Utility Scripts

```bash
# Clean up stale permissions from roles, then export config
drush scr drush/scripts/clean_permissions.php && drush -y cex

# Full migration test rebuild (see script for current state — most steps are commented out)
bash scripts-dev/rebuild_site.sh
```

### Creating a New Site

```bash
composer generate-site    # Interactive: prompts for production FQDN, creates sites/ dir and config/ dir
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
composer outdated drupal/\*               # Check for Drupal package updates
```

## Architecture

### Multisite Structure

The codebase hosts multiple OSU department sites from a single Drupal installation using the **Domain module** (not Drupal core multisite). Sites share a single database but content is access-controlled by domain.

- `docroot/sites/agsci.oregonstate.edu/` — main site (College of Ag Sciences)
- `docroot/sites/landscapeplants.oregonstate.edu/` — secondary site
- `docroot/sites/sites.php` — maps hostnames to site directories
- `config/agsci-oregonstate-edu/` — config export for the main site (1400+ YAML files)

### Configuration Management

Two config layers exist:

1. **`config/`** — Full Drupal config export (used by `drush cim/cex`)
2. **`config_imports/`** — Partial config imports organized by type (`storage/`, `content_type/`, `fields/`, `display/`) used during migration rebuilds to incrementally install configuration

### Custom Modules (`docroot/modules/custom/`)

All custom modules are OSU WAMS modules pulled via Composer from GitHub (osu-wams and leigh-osu orgs). Key modules:

- **`osu_migrations`** / **`osu_migrations_cas`** — D7→D10 migration definitions (source plugins, process plugins, migration YAML)
- **`osu_standard`** — Installation profile used for fresh site installs
- **`osu_groups`** — Group/organizational content using the Group module
- **`osu_publications`** — Publication content type (formerly Biblio)
- **`osu_editorial_workflow`** — Content moderation configuration
- **`osu_paragraphs`** — Paragraph type definitions
- **`osu_block_types`** / **`osu_custom_blocks`** — Custom block content entities
- **`osu_profile`** — Faculty/staff profile content type
- **`osu_bootstrap_layout_builder`** — Bootstrap grid integration for Layout Builder

### Custom Themes (`docroot/themes/custom/`)

- **`manzanita`** — Primary frontend theme (Bootstrap-based)
- **`madrone`** — Secondary/alternate theme
- Admin theme: `gin` (contrib)

### Migration Architecture

Migrations follow the Drupal Migrate API. The migration pipeline for a full D7→D10 migration runs in this order:

1. User accounts (`OSU Accounts` tag)
2. Media (`OSU Media` tag)
3. Taxonomy (`OSU Taxonomy` tag)
4. Custom blocks (`OSU Custom Blocks` tag)
5. Partial config imports (content types, fields, display)
6. Domain configuration
7. Paragraphs → Layout Builder conversions (individual migration IDs like `paragraph_1_col__to__layout_builder`)
8. Content migrations (pages, books, stories, publications)

Migration source database connection is configured via environment variables (`DRUPAL_MIGRATE_DBNAME`, `DRUPAL_MIGRATE_DBUSER`, `DRUPAL_MIGRATE_DBPASS`).

### Deployment (Acquia Cloud)

- `develop` branch → Pipelines builds `pipelines-build-develop` → Acquia **dev** environment (automatic)
- `main` branch → Pipelines builds `pipelines-build-main` → Acquia **stage** environment (automatic). Promote with `bash scripts-dev/promote_main.sh` — NOT a direct `git push origin develop:main`: `.ddev` is tracked on develop but deliberately kept off main, and the script commits develop's tree minus `.ddev` onto main (one synthetic commit per promote, no force pushes).
- **prod** tracks the `master` ref on Acquia's internal repo; production deploys are manual (Acquia Cloud UI)

Deployment hooks in `hooks/` run `drush updatedb` and `drush cache:rebuild` for all multisite domains in parallel after code deploy.

### Patches

`composer.json` applies 21 patches (stored in `patches/`) to core and contrib modules. When updating packages, check if patches still apply cleanly.

### Drush Aliases

Remote Acquia environments accessible via:
- `drush @osucas.dev` — development
- `drush @osucas.stage` — staging
- `drush @osucas.prod` — production

## Migration Context

This is a Drupal 7 to Drupal 10 content migration project for agsci.oregonstate.edu.

- **D10 codebase (current)**: This directory
- **D7 codebase (legacy)**: Available at `./legacy-d7/` (symlink to original D7 site)
- **D7 database connection**: The `$databases['migrate']` block in `docroot/sites/agsci.oregonstate.edu/settings.local.php` (untracked, per-environment)

### Active Migration Modules

- `migrate_plus`
- `migrate_domain`
- `webform_migrate` (contrib — provides `d7_webform` / `d7_webform_submission`)
- `osu_migrations_cas` (custom — primary migration module for this project)

### Content Types Migration Map

**1:1 mapping:**
150 Species, Article → Story, Art About Agriculture, Biblio → Publication, Course, Degree Fact Sheet, Degree Fact Sheet - Graduate, Enterprise Budgets, Funding Opportunities, Fun Facts, Image Album, Plant Variety Release, Project, Story, Video, Weather daily/monthly/data, Weed, Basic page, Webform

**Consolidating to existing types:**
- Book page, Feature Page, Paragraph page → Basic page
- Feature Story → Story

**Manual recreation required:** Feed

**NOT migrating:** Announcement, Degree, FAQ, Highlight, Multi Menu, Navigation Grid, Poster, Sidebar Carousel, Simple Tab, Slide Show, Stylesheet Overlay

**Webforms:** `d7_webform` (webform_migrate) creates one `webform_<nid>` config entity per D7 webform node (elements, email handlers, conditionals → `#states`); `cas_webform_to_webform_node` migrates the nodes and links each to its form; `cas_webform_group_content` places them in groups (runs with the `CAS Groups` tag). Submissions (`d7_webform_submission`, ~33k rows incl. PII) import last; orphaned D7 submissions whose webform node was deleted are skipped via a `migration_lookup` added in `osu_migrations_cas_migration_plugins_alter()`.

### Complex Migration Requirements

**Paragraphs:** D7 field collections/paragraphs → D10 paragraph entities. Paragraph migrations must run before node migrations. Order and nesting must be preserved.

**Groups (Organic Groups → Group module):** D7 Parent Unit and Group content types → D10 Group. Group membership and permissions migration is mostly complete.

**Domain:** Multi-site content uses `migrate_domain` for domain assignments.

### Migration File Locations

- Migration configs: `docroot/modules/custom/osu_migrations_cas/migrations/`
- Source plugins: `docroot/modules/custom/osu_migrations_cas/src/Plugin/migrate/source/`
- Process plugins: `docroot/modules/custom/osu_migrations_cas/src/Plugin/migrate/process/`
- D7 database settings: `$databases['migrate']` in `docroot/sites/agsci.oregonstate.edu/settings.local.php`
- D7 custom modules: `legacy-d7/sites/all/modules/custom/`

### Migration Dependency Order

1. Taxonomy terms (before any node with term references)
2. Users/accounts
3. Files and media
4. Paragraphs/field collections (before nodes that reference them)
5. Groups (before group content)
6. Nodes
7. Domain assignments
8. Aliases and redirects
9. Group memberships

### Migration Patterns in osu_migrations_cas

- Use `d7_node_domain_access` source plugin (not core `d7_node`) — it adds domain fields
- Add `no_stub: true` to all `migration_lookup` plugins to prevent stub entity creation
- Add `skip_on_empty: row` after `migration_lookup` inside `sub_process` to drop null references
- All migration configs use `migrate_plus.migration.*` filename prefix
- Check existing migrations in `osu_migrations` for established patterns before writing new ones