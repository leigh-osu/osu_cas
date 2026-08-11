#!/bin/bash

# Drupal Multisite Rebuild Script for Migration Testing
# This script rebuilds a fresh Drupal site from scratch.
#
# The script is split into sections, broken between each `ddev snapshot`
# create and the matching `ddev snapshot restore`. Each section ends with a
# snapshot create; the next section begins by restoring that snapshot, so any
# section can be run independently to resume from its checkpoint.
#
# Usage:
#   bash scripts-dev/rebuild_site.sh              # show this section help
#   bash scripts-dev/rebuild_site.sh all          # run all sections
#   bash scripts-dev/rebuild_site.sh 3            # run only section 3
#   bash scripts-dev/rebuild_site.sh from 3       # run section 3 onward (3..6)
#   bash scripts-dev/rebuild_site.sh list         # list sections and exit
#
# Each section is also a shell function (section_1 .. section_7) and can be
# sourced and called directly:
#   source scripts-dev/rebuild_site.sh list && section_3
#
# Sections:
#   1  install + accounts + media        -> snapshot aftermedia
#   2  taxonomy + custom blocks          -> snapshot afterosublocks
#   3  config + paragraphs               -> snapshot afterparagraphs
#   4  nodes                             -> snapshot afternodes
#   5  pages + stories + webforms        -> snapshot afterparanodes
#   6  groups                            -> snapshot aftergroups
#   7  aliases + redirects + profiles + menus + blocks

# set -e  # Exit on any error

# Configuration
SITE_NAME="College of Agricultural Sciences"
ADMIN_USER="cws_dpla"
ADMIN_PASS="ok"
ADMIN_EMAIL="noreply@mail.drupal.oregonstate.edu"
PROFILE="osu_standard"
SITE_URI="agsci.oregonstate.edu"  # For multisite (sites/ directory uses dots)
CONFIG_DIR="agsci-oregonstate-edu"  # Config directory uses dashes

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Create a named snapshot, removing any existing snapshot of the same name
# first so `ddev snapshot` cannot fail on a name collision.
snapshot_save() {
  local name="$1"
  # Delete only this named snapshot (not --cleanup, which wipes them all),
  # ignoring "not found" so the first run is fine too. DDEV writes snapshots as
  # "<name>-<dbtype>_<version>.zst" (e.g. aftermedia-mysql_8.0.zst); older DDEV
  # used a "<name>" directory. Match all three forms. The "-"/"." boundary
  # keeps "afternodes" from also matching "afterparanodes".
  rm -rf "${PROJECT_ROOT}/.ddev/db_snapshots/${name}" \
         "${PROJECT_ROOT}/.ddev/db_snapshots/${name}".* \
         "${PROJECT_ROOT}/.ddev/db_snapshots/${name}"-* 2>/dev/null || true
  ddev snapshot --name "${name}"
}

# ---------------------------------------------------------------------------
# Section 1: install + accounts + media  -> snapshot aftermedia
# ---------------------------------------------------------------------------
section_1() {
  echo "=== Section 1: install + accounts + media ==="

  # Refresh the D7 source (database + files) from the latest Acquia
  # nightly backup (synced locally in the mornings) so the migration
  # never runs from a stale snapshot — live-D7 edits newer than the
  # local copy were the cause of stale content and missing files.
  echo "Refreshing D7 source from the Acquia nightly backup..."
  bash /Users/leighr/Sites/osu/agscid7/localscripts/load_acquia_backup.sh --site agsci

  # Stop and restart DDEV to reset database
  echo "Resetting DDEV environment..."
  ddev stop -O -R

  # Reset the files directories along with the database. upgrade_d7_files
  # copies with file_copy's default `replace`, so every rebuild re-copies all
  # ~50k public files regardless -- wiping first costs deletion time, not copy
  # time. Without it the tree only grows: files deleted in D7 linger forever,
  # and any change of destination path (the year subdirectories) strands the
  # previous run's copies. Local files had drifted to 113G against a 92G D7
  # source, ~21G of it orphaned. Starting empty is also the only way a file
  # the migration failed to produce, or a URL it failed to rewrite, fails
  # locally the way it would in production instead of resolving off a stale
  # copy. Section 7 re-seeds its own assets (import_stage_redesigns.php).
  echo "Purging local files directories..."
  # Anchor the paths before deleting anything: an unset PROJECT_ROOT would
  # otherwise aim this at /files, and an unset SITE_URI at docroot/sites//files.
  # composer.json proves PROJECT_ROOT really is the project checkout.
  if [ -z "${PROJECT_ROOT}" ] || [ -z "${SITE_URI}" ] || [ ! -f "${PROJECT_ROOT}/composer.json" ]; then
    echo "  refusing to purge: PROJECT_ROOT/SITE_URI not sane (${PROJECT_ROOT} / ${SITE_URI})" >&2
  else
    for dir in "${PROJECT_ROOT}/docroot/sites/${SITE_URI}/files" \
               "${PROJECT_ROOT}/files/agsci/private-files"; do
      # Belt and braces: only ever a *files* directory inside the project.
      case "${dir}" in
        "${PROJECT_ROOT}"/*/files|"${PROJECT_ROOT}"/*/private-files) ;;
        *)
          echo "  refusing to purge unexpected path: ${dir}" >&2
          continue
          ;;
      esac
      if [ -d "${dir}" ]; then
        echo "  purging ${dir} ($(du -sh "${dir}" 2>/dev/null | cut -f1))"
        rm -rf "${dir}"
      fi
      mkdir -p "${dir}"
    done
  fi

  ddev start

  # Install Drupal
  echo "Installing Drupal with ${PROFILE} profile..."

  ddev drush site:install ${PROFILE} \
    --site-name="${SITE_NAME}" \
    --account-name="${ADMIN_USER}" \
    --account-pass="${ADMIN_PASS}" \
    --account-mail="${ADMIN_EMAIL}" \
    --sites-subdir="${SITE_URI}" \
    --yes

  # ddev import-db --file="/Users/leighr/Sites/osu/osu_cas/scripts-dev/agsci_oregonstate_edu_1772688701.sql"

  # site:install (core 10.6.12 / drush 13.7.5) leaves group's cached
  # group-type -> plugin map stale, missing basic_group-group_membership.
  # Group reruns installEnforced() on every subsequent module install
  # (hook_modules_installed), so without this rebuild every drush en below
  # throws "'group_content_type' entity with ID 'basic_group-group_membership'
  # already exists" after installing its modules.
  ddev drush cr -y

  # Adopt the committed site UUID so every rebuild matches the exported
  # config: full `drush config:import` refuses to run against a mismatched
  # site uuid, and this stops system.site churn in the export. Config-entity
  # uuids still regenerate per rebuild — that churn stays out of the repo
  # until the site stops being recreated.
  SITE_UUID=$(grep '^uuid:' "${PROJECT_ROOT}/config/${CONFIG_DIR}/system.site.yml" | awk '{print $2}')
  if [ -n "${SITE_UUID}" ] && [ "${SITE_UUID}" != "null" ]; then
    ddev drush config:set system.site uuid "${SITE_UUID}" -y
  fi

  # Install modules
  echo "Installing modules..."

  # osu_publications' config/install objects (node.type.publications, the
  # default form/view displays) declare config dependencies on these contrib
  # modules, but osu_publications.info.yml does not list them as module
  # dependencies. So drush won't auto-enable them, and enabling osu_publications
  # first aborts with UnmetDependenciesException -- which, because this script
  # runs without `set -e`, silently cascades: no custom migration modules get
  # enabled and every later migrate:import fails as an "invalid migration ID",
  # leaving an empty site. Enable the config-dependency modules up front.
  ddev drush en field_group date_ap_style node_revision_delete toc_js toc_js_per_node -y

  ddev drush en osu_icon_field osu_publications -y
  ddev drush en domain domain_access domain_alias domain_content domain_source multiselect layout_builder_modal layout_builder_reorder -y
  ddev drush en migrate migrate_drupal phpass migrate_plus -y
  ddev drush en osu_migrations osu_user_accounts osu_migrations_files osu_migrations_media osu_migrations_taxonomy \
      osu_migrate_content og_to_group paragraphs_to_layout_builder osu_user_to_profiles devel migrate_devel \
      domain_migrate domain_access_migrate webform_migrate -y

  # osu_digital_measures provides the osu_digital_measures_report field formatter
  # used by node.osu_profile display config (field_profile_dm_pubs / _awards). It
  # must be enabled before `config:import ../config_imports/display` in section 3,
  # otherwise that partial import aborts on the unmet module dependency and NO
  # display config is applied.
  ddev drush en osu_digital_measures -y

  # osu_cas_multisite: shared multisite helpers (group breadcrumbs, colorbox
  # caption preprocess, Layout Builder UX); its degrees submodule carries the
  # degree-fact-sheet node template.
  ddev drush en osu_cas_multisite osu_cas_multisite_degrees -y


  ddev drush cr -y

  ddev drush theme:install manzanita -y

  ddev drush config:set system.theme default manzanita -y

  # Show all errors/warnings with backtraces while migration testing is
  # active (local only; remove when testing winds down).
  ddev drush config:set -y system.logging error_level verbose

  ddev drush config:set -y system.performance css.preprocess 0
  ddev drush config:set -y system.performance js.preprocess 0
  ddev drush config:set -y system.performance cache.page.max_age 0

  ddev drush config:set system.date timezone.default America/Los_Angeles -y
  ddev drush config:set system.date timezone.user.configurable 0 -y

  ddev drush cr -y
  ddev drush ev "node_access_rebuild();"

  # end install

  ddev drush migrate:import d7_domain

  ddev drush migrate:import upgrade_d7_users_with_roles

  ddev drush migrate:import --tag='OSU Accounts'

  # Base profile nodes immediately after accounts: content migrations in
  # later sections reference people through the uid -> profile-nid map this
  # creates (course instructor, DFS advisor, project leader/member, story
  # employee, site coordinators). The nodes stay skeletal until the
  # section-7 'OSU Drupal Profile' field migrations fill them in.
  #
  # Content migrations preserve their D7 nids AND vids (revision ids). The
  # profile migration auto-increments both, so running it early it would
  # otherwise plant profiles on low nids/vids that later collide with the
  # preserved D7 nid/vid of a content node — the nid clash gives "Update
  # existing node revision while changing the revision ID is not supported",
  # the vid clash gives "Duplicate entry for key node__vid". Seed both
  # auto-increments above the D7 maxima (frozen source: max nid 287726, max
  # vid 1115981) so every early profile lands well above all D7 ids, leaving
  # every D7 nid and vid free.
  ddev drush sql:query "ALTER TABLE node AUTO_INCREMENT = 300000;"
  ddev drush sql:query "ALTER TABLE node_revision AUTO_INCREMENT = 1200000;"

  # Enable osu_migrations_cas BEFORE the profile migration: its
  # hook_migration_plugins_alter() swaps upgrade_d7_user_to_profile onto the
  # cas_d7_user_profile_domain source and maps field_domain_access from each
  # user's D7 domain_editor rows. Enabled later, the profile migration runs with
  # the stock d7_user source and every profile lands with NO domain assignment.
  # (Also provides cas_user_authmap below, and pulls in the cas module.)
  ddev drush en osu_migrations_cas -y

  # Recently-active role-less users (352 as of the 2026-07 audit): no
  # editorial role, so upgrade_d7_users_with_roles skips them, but they
  # logged in via CAS within the fixed window (see login_since in the
  # migration) — mostly faculty/staff who maintain their own profile. Must
  # run BEFORE upgrade_d7_user_to_profile (whose uid lookup, altered in
  # osu_migrations_cas, consults this migration's map so their profiles are
  # owned by them instead of uid 1) and before cas_user_authmap below.
  ddev drush migrate:import cas_d7_active_users

  ddev drush migrate:import upgrade_d7_user_to_profile

  # CAS login mappings (uid -> externalauth authmap, provider 'cas').
  # The stock contrib upgrade_d7_cas_user migration silently de-registers here:
  # its d7_cas_user source declares source_module=cas, and our D7 source has the
  # cas module flagged disabled (system.status=0) even though the cas_user table
  # is fully populated, so checkRequirements() filters it out. cas_user_authmap
  # (osu_migrations_cas) uses the osu_cas_user source, which gates on the
  # cas_user TABLE instead of the module flag, so it works durably across source
  # re-imports with no manual {system} edit. Only users migrated by
  # upgrade_d7_users_with_roles get a mapping; the rest are skipped.
  # cas_user_authmap is defined in osu_migrations_cas (enabled just above).
  ddev drush migrate:import cas_user_authmap

  # drush migrate:import --tag aborts the remaining migrations in the tag as
  # soon as one migration reports failed rows (MigrateRunnerCommands throws
  # after each migration with failures). A single corrupt source image in
  # upgrade_d7_media_images is enough to starve every non-image media
  # migration, and the missing upgrade_d7_media_documents then gates out
  # cas_course/project/video/article/og_group downstream. Import each media
  # migration individually, in dependency order (files before media), so one
  # migration's failed rows can't stop the others.
  ddev drush migrate:import upgrade_d7_file_private
  ddev drush migrate:import upgrade_d7_files
  ddev drush migrate:import upgrade_d7_media_images
  ddev drush migrate:import upgrade_d7_media_documents
  ddev drush migrate:import upgrade_d7_media_audio
  ddev drush migrate:import upgrade_d7_media_kaltura
  ddev drush migrate:import upgrade_d7_media_local_video
  ddev drush migrate:import upgrade_d7_media_remote_video
  # Private-scheme media (the stock media migrations cover public only);
  # OsuMediaEmbed resolves wysiwyg tokens against these via the
  # osu_migrations-private-image-media-lookup patch.
  ddev drush migrate:import cas_media_private_images
  ddev drush migrate:import cas_media_private_documents

  snapshot_save aftermedia
}

# ---------------------------------------------------------------------------
# Section 2: taxonomy + custom blocks  -> snapshot afterosublocks
# ---------------------------------------------------------------------------
section_2() {
  echo "=== Section 2: taxonomy + custom blocks ==="

  ddev snapshot restore aftermedia

  ddev drush migrate:import d7_image_styles
  ddev drush migrate:import --tag='OSU Taxonomy'

  ddev drush migrate:import --tag='OSU Custom Blocks' --force

  ddev drush pqe -y

  snapshot_save afterosublocks
}

# ---------------------------------------------------------------------------
# Section 3: config + paragraphs  -> snapshot afterparagraphs
# ---------------------------------------------------------------------------
section_3() {
  echo "=== Section 3: config + paragraphs ==="

  ddev snapshot restore afterosublocks

  ddev drush en osu_migrations_cas -y

  echo 'installing storage'
  ddev drush config:import --partial --source=../config_imports/storage -y
  echo 'installing content types'
  ddev drush config:import --partial --source=../config_imports/content_type -y
  echo 'installing fields'
  ddev drush config:import --partial --source=../config_imports/fields -y
  echo 'installing display'
  ddev drush config:import --partial --source=../config_imports/display -y
  echo 'installing other configs'
  ddev drush config:import --partial --source=../config_imports -y

  # Story tags rename: the working field is field_tax_story_tags targeting
  # the story_tags vocabulary (created in section 2 by the vocabulary
  # migration via the CAS machine-name remap). Prune the profile-installed
  # leftovers: osu_story's field_tags/field_tax_tags and the stock "tags"
  # vocabulary. Runs before node migrations, so the fields are still empty.
  ddev drush php:eval '
    foreach (["field_tags", "field_tax_tags"] as $name) {
      \Drupal\field\Entity\FieldConfig::loadByName("node", "story", $name)?->delete();
      \Drupal\field\Entity\FieldStorageConfig::loadByName("node", $name)?->delete();
    }
    \Drupal\taxonomy\Entity\Vocabulary::load("tags")?->delete();
    field_purge_batch(100);
    print "legacy tags fields + vocabulary pruned\n";'

  # ddev drush config:delete taxonomy.vocabulary.publication_type -y

  ddev drush cr -y

  ddev drush migrate:import field_collection_field_lp_adj_column__to__layout_builder
  ddev drush migrate:import field_collection_field_lp_picbox__to__layout_builder
  ddev drush migrate:import paragraph_1_col_clean__to__layout_builder
  ddev drush migrate:import paragraph_1_column_full_width__to__layout_builder
  ddev drush migrate:import paragraph_3_col_center__to__layout_builder
  ddev drush migrate:import paragraph_3_col_left__to__layout_builder
  ddev drush migrate:import paragraph_3_col_right__to__layout_builder
  ddev drush migrate:import paragraph_4_column_col1__to__layout_builder
  ddev drush migrate:import paragraph_4_column_col2__to__layout_builder
  ddev drush migrate:import paragraph_4_column_col3__to__layout_builder
  ddev drush migrate:import paragraph_4_column_col4__to__layout_builder
  ddev drush migrate:import paragraph_accordian__to__layout_builder
  ddev drush migrate:import paragraph_accordion__to__layout_builder
  ddev drush migrate:import paragraph_alert_message__to__layout_builder
  # paragraph_divider__to__layout_builder intentionally not run: dividers are
  # migrated as component-less Layout Builder sections (min-height + background)
  # by CasParagraphsLayout, not as empty blocks.
  ddev drush migrate:import paragraph_menu__to__layout_builder
  ddev drush migrate:import paragraph_1_col__to__layout_builder
  ddev drush migrate:import paragraph_1_column_background_video__to__layout_builder
  ddev drush migrate:import paragraph_2_col_left__to__layout_builder
  ddev drush migrate:import paragraph_2_col_right__to__layout_builder
  ddev drush migrate:import paragraph_2_column_4_8_left__to__layout_builder
  ddev drush migrate:import paragraph_2_column_4_8_right__to__layout_builder
  ddev drush migrate:import paragraph_2_column_8_4_left__to__layout_builder
  ddev drush migrate:import paragraph_2_column_8_4_right__to__layout_builder
  ddev drush migrate:import paragraph_sacnas_officer_body_text__to__layout_builder
  ddev drush migrate:import paragraph_lp_picbox_grid__to__layout_builder
  ddev drush migrate:import paragraph_lp_vertical_tabs__to__layout_builder
  ddev drush migrate:import paragraph_lp_adjustable_columns__to__layout_builder

  ddev drush cr -y

  ddev drush pqe -y

  snapshot_save afterparagraphs
}

# ---------------------------------------------------------------------------
# Section 4: nodes  -> snapshot afternodes
# ---------------------------------------------------------------------------
section_4() {
  echo "=== Section 4: nodes ==="

  ddev snapshot restore afterparagraphs

  ddev drush migrate:import cas_150_species_to_150_species
  ddev drush migrate:import cas_aaa_to_aaa
  ddev drush migrate:import cas_course_to_course
  ddev drush migrate:import cas_dfs_to_dfs
  ddev drush migrate:import cas_dfsg_to_dfsg
  ddev drush migrate:import cas_enterprise_budgets_to_enterprise_budgets
  ddev drush migrate:import cas_fun_facts_to_fun_facts
  ddev drush migrate:import cas_funding_opportunities_to_funding_opportunities
  ddev drush migrate:import cas_image_album_to_image_album
  ddev drush migrate:import cas_project_to_project
  ddev drush migrate:import cas_pvr_to_pvr
  ddev drush migrate:import cas_video_to_video
  ddev drush migrate:import cas_weed_to_weed
  ddev drush migrate:import cas_weather_data_to_weather_daily_data
  ddev drush migrate:import cas_weather_daily_data_to_weather_daily_data
  ddev drush migrate:import cas_weather_monthly_data_to_weather_monthly_data
  # Dictionaries first: the publication migration's keyword and
  # author/editor sub_process pipelines look terms up via these maps
  # (kid and cid respectively).
  ddev drush migrate:import cas_biblio_keywords
  ddev drush migrate:import cas_biblio_authors
  ddev drush migrate:import upgrade_d7_biblio_publication

  # Maximise D7 image text on media. The media migration already carries
  # file-level alt/title; these two backfill from the D7 per-delta *field*
  # alt/title where the media is still empty, then seed field_media_caption from
  # the resulting alt/title. Order matters: alt backfill first, caption seed
  # second (it reads the just-backfilled alt/title).
  ddev drush migrate:import cas_media_alt_backfill
  ddev drush migrate:import cas_media_caption_seed

  ddev drush pqe -y

  snapshot_save afternodes
}

# ---------------------------------------------------------------------------
# Section 5: pages + stories + webforms  -> snapshot afterparanodes
# ---------------------------------------------------------------------------
section_5() {
  echo "=== Section 5: pages + stories + webforms ==="

  ddev snapshot restore afternodes

  # ddev drush config:import --partial --source=../docroot/modules/custom/osu_migrations_cas/config/install -y

  ddev drush migrate:import cas_book_to_page
  ddev drush migrate:import cas_page_to_page
  ddev drush migrate:import cas_feature_page_to_page
  ddev drush migrate:import cas_paragraph_page_to_page
  ddev drush migrate:import cas_feature_story_to_story
  ddev drush migrate:import cas_story_to_story
  ddev drush migrate:import cas_article_to_story

  # Webforms. d7_webform (webform_migrate) builds one webform config entity per
  # D7 webform node -- elements, email handlers, conditionals->#states -- keyed
  # webform_<nid>; cas_webform_to_webform_node then creates the webform nodes
  # and links each to its form. Group placement (cas_webform_group_content)
  # runs in section 6 via the 'CAS Groups' tag. Submissions
  # (d7_webform_submission, ~33k rows incl. PII) import last; orphaned D7 rows
  # whose webform node was deleted are skipped via the migration_lookup added
  # in osu_migrations_cas_migration_plugins_alter().
  # The `en` is defensive for runs resumed from a pre-webform snapshot; it is
  # a no-op when section 1 already enabled webform_migrate.
  ddev drush en webform_migrate -y
  ddev drush migrate:import d7_webform
  ddev drush migrate:import cas_webform_to_webform_node
  ddev drush migrate:import d7_webform_submission

  ddev drush pqe -y

  snapshot_save afterparanodes

  # ddev drush ms --tag='CAS Paragraphs'
  # ddev drush ms --tag='OSU Paragraphs' istedmonk.com
  # ddev drush ms --tag='Layout content'
  # ddev drush ms --tag='OSU Configuration'
  # ddev drush ms --tag='OSU Content'
}

# ---------------------------------------------------------------------------
# Section 6: groups  -> snapshot aftergroups
# ---------------------------------------------------------------------------
section_6() {
  echo "=== Section 6: groups ==="

  ddev snapshot restore afterparanodes

  ddev drush migrate:import upgrade_d7_view_modes

  # Group field config (storage + instances + group_content enablers) must be
  # imported before the CAS group migrations, which populate those fields.
  echo 'installing group settings'
  ddev drush config:import --partial --source=../config_imports/group -y

  ddev drush cr -y

  # Base OG / Parent Unit group creation. These create the group entities
  # (keyed by nid) and satisfy the shared membership / menu / organization
  # migrations, which look up groups by these base migration IDs.
  ddev drush migrate:import upgrade_d7_node_og_group
  ddev drush migrate:import upgrade_d7_node_parent_unit_group

  # CAS field-mapping group migrations. They update the same group entities with
  # the CAS field data (body, contact, location, social, parent unit reference,
  # site coordinators, info-sheet media document, etc.). cas_node_parent_unit_group
  # must run before cas_node_og_group so field_group_parent_unit can resolve.
  ddev drush migrate:import cas_node_parent_unit_group
  ddev drush migrate:import cas_node_og_group
  ddev drush pqe -y

  ddev drush migrate:import upgrade_d7_user_og_memberships
  ddev drush migrate:import upgrade_d7_node_og_organization

  # OG menus -> group menus (links into each group's auto-created menu).
  # OG menus are excluded from the generic upgrade_d7_menu / upgrade_d7_menu_links
  # migrations by osu_migrations_cas_migration_plugins_alter (cas_skip_og_menu),
  # so this is the sole importer of OG menu links -- no id:mlid clash with the
  # 'OSU Menus' tag in section 7.
  # Only each group's canonical OG menu goes to its group menu; extra OG menus
  # (The Source's per-issue menus, EMT's Academics, FWCS's Confluence) become
  # standalone menus via cas_og_extra_menu / cas_og_extra_menu_links.
  ddev drush migrate:import cas_og_menu_group_menu
  ddev drush migrate:import cas_og_extra_menu
  ddev drush migrate:import cas_og_extra_menu_links

  ddev drush migrate:import --tag='CAS Groups' --force

  # Book-toc menus -> group menus. Must run AFTER the CAS Groups tag: its
  # requirements gate depends on cas_book_group_content / cas_page_group_content
  # (repointed from the never-run upgrade_d7_* deps by
  # osu_migrations_cas_migration_plugins_alter), and the og_book_menu plugin
  # needs each group's content menu to already exist.
  ddev drush migrate:import upgrade_d7_book_menu_group_menu

  ddev drush pqe -y

  snapshot_save aftergroups
}

# ---------------------------------------------------------------------------
# Section 7: aliases + redirects + profiles + menus + blocks
# ---------------------------------------------------------------------------
section_7() {
  echo "=== Section 7: aliases + redirects + profiles + menus + blocks ==="

  ddev snapshot restore aftergroups

  # ddev drush migrate:import --tag='OSU Groups' --force
  ddev drush migrate:import --tag='OSU Alias'
  ddev drush migrate:import --tag='OSU Redirect'

  # Base profile nodes: upgrade_d7_user_to_profile creates one osu_profile node
  # per user (matched later by uid -> nid), and the generic per-bundle
  # migrations (upgrade_d7_user_profile_osu_*) map the shared base fields.
  ddev drush migrate:import --tag='OSU Drupal Profile'

  # CAS profile field migrations (osu_migrations_cas). One per D7 profile2 bundle;
  # each updates the existing osu_profile node (uid -> nid lookup) with the
  # additional CAS fields, using overwrite_properties so only its own fields are
  # written. Must run after the base 'OSU Drupal Profile' migrations above.
  # cas_user_profile_agricultural_sciences also needs the faculty_expertise terms
  # from --tag='OSU Taxonomy' (section 2).
  ddev drush migrate:import cas_user_profile_osu_person
  ddev drush migrate:import cas_user_profile_osu_employee
  ddev drush migrate:import cas_user_profile_osu_faculty
  ddev drush migrate:import cas_user_profile_osu_student
  ddev drush migrate:import cas_user_profile_agricultural_sciences

  # D7 editor-set focal points -> focal_point crop entities (18k). Runs late:
  # media saves throughout the migration auto-create centered default crops,
  # and this migration UPSERTS them (cas_existing_crop_id) so the D7 focus
  # wins. Feeds the picbox_800x580 style (config_imports/display).
  ddev drush migrate:import cas_focal_points

  # Profile group placement (D7 user OG memberships -> group_node:osu_profile).
  # Deliberately NOT tagged 'CAS Groups' (section 6): it must run after the
  # profile nodes above exist, or every row skips on the profile lookup.
  ddev drush migrate:import cas_profile_group_content --force

  ddev drush migrate:import --tag='OSU Menus'
  ddev drush migrate:import --tag='OSU Blocks'

  # upgrade_d7_block recreates block placements for D7 themes that are not
  # installed in D10 (bootstrap, doug_fir, bartik, seven, larch, adminimal),
  # leaving orphan block.block.* config that breaks every later
  # `drush config:import` ("... depends on the X theme that will not be
  # installed"). Remove those orphans so config import stays clean.
  ddev drush scr scripts-dev/prune_orphan_blocks.php

  # Per-domain config overrides (Domain Config): each domain's front page and
  # site name, pulled from D7. Imported here at the very end of the rebuild so
  # that no earlier config:import or migration can overwrite them. domain_config
  # applies the overrides at runtime; domain_config_ui exposes them in the UI.
  # `drush config:import --partial` only reads the default collection and
  # ignores the domain.<id> config collections, so a helper script writes those
  # collections directly from config_imports/domain/.
  ddev drush en domain_config domain_config_ui -y
  ddev drush scr scripts-dev/import_domain_config.php

  # Group 2.x node access joins group_relationship_field_data without DISTINCT,
  # so nodes in multiple groups repeat in every node listing (group issue
  # #3172135). Force the views "distinct" query option on all node-based views.
  ddev drush scr scripts-dev/set_views_distinct.php

  # Dev helpers not in the section-1 module list: the site-sync block (prod
  # compare window + slideshow) and Simple Styleguide. Enable them here so they
  # are available after every rebuild without a manual install; the site-sync
  # block is then placed in the pre-footer below.
  ddev drush en osu_cas_site_sync simple_styleguide taxonomy_manager -y

  # Import the contrib dev-tool config (Simple Styleguide colour palette and the
  # osu_card / osu_accordion / osu_menu_bar sample patterns). Runs after the
  # module enable above so the styleguide_pattern entity type exists.
  ddev drush config:import --partial --source=../config_imports/contrib -y

  # Make the site-sync block and the styleguide visible to everyone. Granted
  # per-permission (not via a role config import) so other role permissions are
  # left untouched.
  ddev drush role:perm:add anonymous 'use osu cas site sync,access style guide'
  ddev drush role:perm:add authenticated 'use osu cas site sync,access style guide'

  # Create a reusable sample osu_accordion block for the styleguide. The migrated
  # accordion blocks are all inline (non-reusable) and render empty standalone,
  # so the osu_accordion styleguide pattern -- which renders whichever reusable
  # osu_accordion block exists -- needs this sample to show real content.
  ddev drush php:eval '
    $storage = \Drupal::entityTypeManager()->getStorage("block_content");
    if (!$storage->loadByProperties(["type" => "osu_accordion", "info" => "Styleguide sample accordion", "reusable" => 1])) {
      $items = [];
      foreach ([["Overview", "<p>A short summary panel. Click a heading to expand or collapse its content.</p>"], ["Details", "<p>The second panel holds more detail -- this is body text inside an accordion item.</p>"]] as $pair) {
        $item = \Drupal\paragraphs\Entity\Paragraph::create(["type" => "osu_accordion_item", "field_p_accordion_title" => $pair[0], "field_p_accordion_body" => ["value" => $pair[1], "format" => "full_html"]]);
        $item->save();
        $items[] = ["target_id" => $item->id(), "target_revision_id" => $item->getRevisionId()];
      }
      $section = \Drupal\paragraphs\Entity\Paragraph::create(["type" => "osu_accordion_section", "field_p_accordion_heading" => "Sample accordion", "field_osu_paragraph_item" => $items]);
      $section->save();
      \Drupal\block_content\Entity\BlockContent::create(["type" => "osu_accordion", "info" => "Styleguide sample accordion", "reusable" => 1, "field_osu_paragraph_item" => [["target_id" => $section->id(), "target_revision_id" => $section->getRevisionId()]]])->save();
    }'

  ddev drush php:eval '
    if (!\Drupal\block\Entity\Block::load("manzanita_sitesync")) {
      \Drupal\block\Entity\Block::create([
        "id" => "manzanita_sitesync", "theme" => "manzanita",
        "region" => "pre_footer", "weight" => 0, "plugin" => "osu_cas_site_sync",
        "settings" => [
          "id" => "osu_cas_site_sync", "label" => "Prod site sync",
          "label_display" => 0, "provider" => "osu_cas_site_sync",
          "base_url" => "https://agsci.oregonstate.edu", "link_text" => "View D7",
        ],
        "visibility" => [],
      ])->save();
    }'

  # Disable the Group module "Group operations" admin block (the big
  # Add-content / Leave-group block) on the front-facing themes.
  ddev drush php:eval 'foreach (["manzanita_groupoperations", "madrone_groupoperations"] as $id) { if ($b = \Drupal\block\Entity\Block::load($id)) { $b->disable()->save(); } }'

  # Masquerade: Administrator-only (is_admin covers every permission, so no
  # role grants are needed -- and deliberately none are made: dx_administrator
  # and architect must NOT be able to masquerade). The block renders in the
  # highlighted region below the messages block (weight 0) and is only
  # visible to users who hold a masquerade permission.
  ddev drush en masquerade -y
  ddev drush php:eval '
    if (!\Drupal\block\Entity\Block::load("manzanita_masquerade")) {
      \Drupal\block\Entity\Block::create([
        "id" => "manzanita_masquerade", "theme" => "manzanita",
        "region" => "pre_footer", "weight" => 0, "plugin" => "masquerade",
        "settings" => [
          "id" => "masquerade", "label" => "Masquerade",
          "label_display" => "0", "provider" => "masquerade",
        ],
        "visibility" => [],
      ])->save();
    }'

  # No placed account-menu block: the site account links (My OSU Profile,
  # My Groups, My Content) live in the admin toolbar's user tray instead
  # (CasToolbarLinkBuilder in osu_cas_multisite replaces core's
  # View profile / Edit profile links, which expose the raw user entity).

  # Give the platform maintainers' migrated accounts the administrator role
  # (is_admin: true, bypasses all permission checks) so the usual dev logins
  # work as superadmins without hunting for the uid-1 uli link. Keyed by
  # email: migrated uids are stable today but the address is the durable
  # identifier if account migrations ever renumber.
  ddev drush php:eval '
    foreach (["roger.leigh@oregonstate.edu", "sara.monk@oregonstate.edu"] as $mail) {
      $users = \Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["mail" => $mail]);
      if ($u = reset($users)) { $u->addRole("administrator"); $u->save(); print "administrator role -> " . $u->getAccountName() . " (uid " . $u->id() . ")\n"; }
      else { print "WARNING: $mail not found; no administrator role granted\n"; }
    }'

  # Strip media tokens that were already dead in D7 (no file_managed row):
  # the wysiwyg filter leaves unresolvable tokens as raw JSON in the text.
  # Also converts any stragglers now that private media exist. Idempotent.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/reprocess_media_tokens.php

  # Capture each group's landing page (the title-match convention) into
  # field_group_home_page — the header reads only the field now. Idempotent;
  # editor-set values survive.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/populate_group_home_pages.php

  # Carry D7's per-node "hide title" boolean (field_node_hide_title on page
  # and paragraph_page) into exclude_node_title's state list — no migration
  # yml can target state, so it's a post-migration step. Idempotent.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/import_hide_title.php

  # Recreate the stage-redesigned Home / Education / About pages as NEW
  # nodes (export in scripts-dev/stage_redesigns, pulled from the stage DB
  # backup by export_stage_redesigns.php). The D7-migrated originals keep
  # their content but gain a "D7 version: " title prefix, and the public
  # aliases (/home/home, /education, /home/about) repoint to the new
  # nodes. Missing physical assets come from scripts-dev/"D10 assets for
  # Roger". Idempotent (keyed by stage UUIDs). Needs the site URI:
  # entity/file paths resolve against the agsci site dir, not sites/default.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/import_stage_redesigns.php

  # D7 rendered page/book field_picture as a header banner. The page
  # migrations populate field_page_banner_image; this prepends the banner
  # section (field block, page_banner view mode) to each node's layout.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/backfill_page_banner_images.php

  # D7 right sidebars (field_right_sidebar + "Display sidebar content"
  # flag): convert the body section to a 67/33 layout with the sidebar
  # HTML in a light-grey inline block on the right.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/convert_right_sidebars.php

  # Duplicate path aliases (mostly term pages shadowing real nodes, e.g.
  # /source/students): disable the rows that do not match what live D7
  # serves.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/fix_duplicate_aliases.php

  # D7 Context-module custom block placements: theme block config for
  # path/type-scoped contexts (The Source header, social widgets, MES
  # headers, weather links) and Layout Builder insertions for the
  # explicit node lists (BMSB News, EMT AP Contacts, NW Plant Eval menu).
  ddev drush --uri="${SITE_URI}" scr scripts-dev/place_context_blocks.php

  # Empty D7 paragraphs migrate into empty inline blocks that render as
  # invisible padded boxes; prune them (colored/background/spacer
  # components and divider bands are preserved).
  ddev drush --uri="${SITE_URI}" scr scripts-dev/prune_empty_layout_blocks.php

  # D7 spacer.gif hack rows (/home/alumni, mycas CARE page): remove the
  # spacer-only sections and express the D7 gaps as section padding on
  # their neighbors instead.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/remove_spacer_blocks.php

  # Adjustable-columns and menu paragraphs migrate into a carrier block that
  # the layout replaces with its children, leaving the carrier unreachable
  # (4,986 blocks, half of all orphaned block_content). Delete them now that
  # every layout has been built and read them.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/prune_intermediate_blocks.php

  # Video cleanup: trim YouTube playlist junk the oEmbed endpoint rejects
  # (also handled in-migration by cas_clean_youtube_url) and unpublish the
  # 20 videos whose remote targets are provider-deleted or private.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/fix_dead_videos.php

  # External stories: create the redirects the osu_story hook misses during
  # migration (~400) and resolve the ~870 targets that point at the D7
  # hostname (self-loops cleared, D7 file URLs rewritten to local files,
  # dead targets cleared, leftovers to files/external_stories_unresolved.csv).
  ddev drush --uri="${SITE_URI}" scr scripts-dev/fix_external_stories.php

  # Legacy file refs whose files are gone everywhere (the section-1 backup
  # refresh recovers anything recoverable first): drop the broken <img>
  # tags and unwrap dead links.
  ddev drush --uri="${SITE_URI}" scr scripts-dev/strip_dead_legacy_refs.php

  # Two degree fact sheets are group-members-only on D7 (OG group_access=1,
  # anonymous 403): Soil Science graduate 249691 and Crop and Soil Science
  # B.S. 250541. The Group-module world has no per-node equivalent, so match
  # D7's effective visibility by unpublishing them (Roger, 2026-08-06).
  ddev drush --uri="${SITE_URI}" php:eval '
    foreach ([249691, 250541] as $nid) {
      if (($n = \Drupal\node\Entity\Node::load($nid)) && $n->isPublished()) {
        $n->setUnpublished();
        $n->save();
        print "unpublished $nid\n";
      }
    }'

  # Migrated inline blocks arrive with block_content's base-field default
  # reusable = TRUE; every reusable block becomes an entry in Layout
  # Builder's "Add block" chooser, which made the dialog build ~17k links
  # (16.5k bogus plugins). Inline blocks are not reusable — flip them.
  # Must run at the END of the pipeline: the paragraph -> Layout Builder
  # conversions that create these blocks run in sections 3-5, so a
  # section-2 flip misses all of them. Placed 'basic' blocks (larch/
  # footer content) keep their flag.
  ddev drush sql:query "UPDATE block_content_field_data SET reusable = 0 WHERE type IN ('osu_card','paragraph_block','osu_accordion') AND reusable = 1"

  # Keep external stories viewable as local pages while migration testing
  # is active (local only; toggle at /admin/config/content/osu-story).
  # Must run AFTER migration and fix_external_stories.php: the osu_story
  # save hooks are gated by this flag, so switching it off earlier would
  # leave every external story without its redirect/metatag treatment.
  # The redirect entities are all created above; this only suppresses them
  # at request time — re-enabling forwards instantly, no catch-up needed.
  ddev drush --uri="${SITE_URI}" config:set -y osu_story.settings auto_forward_external 0
  ddev drush cr
  # Each site directory compiles its own container; the bare cr above only
  # rebuilds the default one. Without this, the agsci container (used by the
  # browser and by --uri drush) stays stale after the section-7 module
  # enables — e.g. masquerade's route access checks aren't registered and
  # rendering the account menu throws "No check has been registered for
  # access_check.masquerade.unmasquerade".
  ddev drush --uri="${SITE_URI}" cr

  echo ""
  echo "=== Site rebuild complete! ==="
  ddev drush uli
}

# ---------------------------------------------------------------------------
# Section 8 (verify): post-rebuild verification — no DB state change.
# Runs verify_migration.php in the web container; non-zero exit on regression.
# Safe to run independently and idempotent.
# ---------------------------------------------------------------------------
section_verify() {
  echo "=== Section 8: post-rebuild verification ==="
  bash "${PROJECT_ROOT}/scripts-dev/verify_migration.sh"
  local verify_rc=$?
  # Refresh the dead legacy-file-URL report (see the script header for the
  # column semantics); informational only, never fails the run.
  ddev drush scr scripts-dev/generate_dead_legacy_file_urls.php || true
  return $verify_rc
}

# ---------------------------------------------------------------------------
# Dispatcher
# ---------------------------------------------------------------------------
list_sections() {
  cat <<'EOF'
Sections:
  1       install + accounts + media                   -> snapshot aftermedia
  2       taxonomy + custom blocks                     -> snapshot afterosublocks
  3       config + paragraphs                          -> snapshot afterparagraphs
  4       nodes                                        -> snapshot afternodes
  5       pages + stories + webforms                   -> snapshot afterparanodes
  6       groups                                       -> snapshot aftergroups
  7       aliases + redirects + profiles + menus + blocks
  verify  post-rebuild verification (no DB changes; exits non-zero on regression)
EOF
}

run_from() {
  local start="$1"
  for n in 1 2 3 4 5 6 7; do
    if [ "${n}" -ge "${start}" ]; then
      "section_${n}"
    fi
  done
  # Always run verification at the end of a multi-section run; the script
  # has `set -e` disabled, so a non-zero exit here is surfaced but doesn't
  # roll anything back.
  section_verify
}

# When sourced, just expose the functions (no dispatch).
if [ "${BASH_SOURCE[0]}" != "${0}" ]; then
  return 0 2>/dev/null
fi

# Prevent idle sleep for the duration of this rebuild (macOS). -w $$ ties
# caffeinate's lifetime to this script's PID, so it exits automatically when
# the rebuild ends.
command -v caffeinate >/dev/null 2>&1 && caffeinate -i -w $$ &

case "${1:-}" in
  "")
    echo "Usage: $0 [all | <1-7> | from <1-7> | verify | list]"
    echo ""
    list_sections
    ;;
  all)
    echo "=== Rebuilding Drupal site for migration testing ==="
    run_from 1
    ;;
  from)
    if ! [[ "${2}" =~ ^[1-7]$ ]]; then
      echo "Usage: $0 from <1-7>" >&2
      exit 1
    fi
    echo "=== Rebuilding Drupal site from section ${2} ==="
    run_from "${2}"
    ;;
  list|-l|--list|-h|--help)
    list_sections
    ;;
  verify|8)
    echo "=== Running verification only ==="
    section_verify
    ;;
  [1-7])
    echo "=== Running section ${1} only ==="
    "section_${1}"
    ;;
  *)
    echo "Unknown argument: ${1}" >&2
    list_sections >&2
    exit 1
    ;;
esac
