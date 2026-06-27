
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
#   5  pages + stories                   -> snapshot afterparanodes
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

  # Stop and restart DDEV to reset database
  echo "Resetting DDEV environment..."
  ddev stop -O -R
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

  # Install modules
  echo "Installing modules..."

  ddev drush en osu_icon_field osu_publications -y
  ddev drush en domain domain_access domain_alias domain_content multiselect layout_builder_modal -y
  ddev drush en migrate migrate_drupal phpass migrate_plus -y
  ddev drush en osu_migrations osu_user_accounts osu_migrations_files osu_migrations_media osu_migrations_taxonomy \
      osu_migrate_content og_to_group paragraphs_to_layout_builder osu_user_to_profiles devel migrate_devel \
      domain_migrate domain_access_migrate -y

  # osu_digital_measures provides the osu_digital_measures_report field formatter
  # used by node.osu_profile display config (field_profile_dm_pubs / _awards). It
  # must be enabled before `config:import ../config_imports/display` in section 3,
  # otherwise that partial import aborts on the unmet module dependency and NO
  # display config is applied.
  ddev drush en osu_digital_measures -y


  ddev drush cr -y

  ddev drush theme:install manzanita -y

  ddev drush config:set system.theme default manzanita -y

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

  # CAS login mappings (uid -> externalauth authmap, provider 'cas').
  # The stock contrib upgrade_d7_cas_user migration silently de-registers here:
  # its d7_cas_user source declares source_module=cas, and our D7 source has the
  # cas module flagged disabled (system.status=0) even though the cas_user table
  # is fully populated, so checkRequirements() filters it out. cas_user_authmap
  # (osu_migrations_cas) uses the osu_cas_user source, which gates on the
  # cas_user TABLE instead of the module flag, so it works durably across source
  # re-imports with no manual {system} edit. Only users migrated by
  # upgrade_d7_users_with_roles get a mapping; the rest are skipped.
  # cas_user_authmap is defined in osu_migrations_cas, which is otherwise not
  # enabled until section 3 — enable it here (pulls in the cas module) so the
  # migration ID is valid at this point.
  ddev drush en osu_migrations_cas -y
  ddev drush migrate:import cas_user_authmap

  ddev drush migrate:import --tag='OSU Media'

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
  ddev drush migrate:import paragraph_divider__to__layout_builder
  ddev drush migrate:import paragraph_menu__to__layout_builder
  ddev drush migrate:import paragraph_1_col__to__layout_builder
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
  ddev drush migrate:import upgrade_d7_biblio_publication

  ddev drush pqe -y

  snapshot_save afternodes
}

# ---------------------------------------------------------------------------
# Section 5: pages + stories  -> snapshot afterparanodes
# ---------------------------------------------------------------------------
section_5() {
  echo "=== Section 5: pages + stories ==="

  ddev snapshot restore afternodes

  # ddev drush config:import --partial --source=../docroot/modules/custom/osu_migrations_cas/config/install -y

  ddev drush migrate:import cas_book_to_page
  ddev drush migrate:import cas_page_to_page
  ddev drush migrate:import cas_feature_page_to_page
  ddev drush migrate:import cas_paragraph_page_to_page
  ddev drush migrate:import cas_feature_story_to_story
  ddev drush migrate:import cas_story_to_story
  ddev drush migrate:import cas_article_to_story

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

  ddev drush migrate:import upgrade_d7_user_og_memberships
  ddev drush migrate:import upgrade_d7_node_og_organization

  # OG menus -> group menus (links into each group's auto-created menu).
  # OG menus are excluded from the generic upgrade_d7_menu / upgrade_d7_menu_links
  # migrations by osu_migrations_cas_migration_plugins_alter (cas_skip_og_menu),
  # so this is the sole importer of OG menu links -- no id:mlid clash with the
  # 'OSU Menus' tag in section 7.
  ddev drush migrate:import cas_og_menu_group_menu

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

  ddev drush pqe -y
  ddev drush cr

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
  5       pages + stories                              -> snapshot afterparanodes
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
