#!/bin/bash
# In-place re-import of the full CAS migration pipeline with --update.
#
# Unlike rebuild_site.sh, this performs NO site:install, NO config:import, and
# NO snapshot restore -- the current database is preserved. Every migration is
# re-run with --update so changed source rows refresh existing destination
# content in place. Use this to pull fresh D7 source data into an already-built
# site without a full from-scratch rebuild.
#
# Order matches rebuild_site.sh sections 1-7 (migrate:import steps only) so
# dependency ordering is preserved. Individual migration failures are logged and
# skipped rather than aborting the whole run; a summary is printed at the end.
#
# Usage:
#   bash scripts-dev/reimport_update.sh

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}" || exit 1

FAILED=()

run() {
  local desc="$1"; shift
  echo ""
  echo "===== [$(date '+%H:%M:%S')] migrate:import ${desc} ====="
  if ! ddev drush migrate:import "$@" --update; then
    echo "!!!!! FAILED: ${desc}"
    FAILED+=("${desc}")
  fi
}

# --- Section 1: domain + accounts + media ---
run "d7_domain"                       d7_domain
run "upgrade_d7_users_with_roles"     upgrade_d7_users_with_roles
run "tag:OSU Accounts"                --tag='OSU Accounts'
run "cas_user_authmap"                cas_user_authmap
run "tag:OSU Media"                   --tag='OSU Media'

# --- Section 2: taxonomy + custom blocks ---
run "d7_image_styles"                 d7_image_styles
run "tag:OSU Taxonomy"                --tag='OSU Taxonomy'
run "tag:OSU Custom Blocks"           --tag='OSU Custom Blocks' --force

# --- Section 3: paragraphs -> layout builder ---
for m in \
  field_collection_field_lp_adj_column__to__layout_builder \
  field_collection_field_lp_picbox__to__layout_builder \
  paragraph_1_col_clean__to__layout_builder \
  paragraph_1_column_full_width__to__layout_builder \
  paragraph_3_col_center__to__layout_builder \
  paragraph_3_col_left__to__layout_builder \
  paragraph_3_col_right__to__layout_builder \
  paragraph_4_column_col1__to__layout_builder \
  paragraph_4_column_col2__to__layout_builder \
  paragraph_4_column_col3__to__layout_builder \
  paragraph_4_column_col4__to__layout_builder \
  paragraph_accordian__to__layout_builder \
  paragraph_accordion__to__layout_builder \
  paragraph_alert_message__to__layout_builder \
  paragraph_divider__to__layout_builder \
  paragraph_menu__to__layout_builder \
  paragraph_1_col__to__layout_builder \
  paragraph_2_col_left__to__layout_builder \
  paragraph_2_col_right__to__layout_builder \
  paragraph_2_column_4_8_left__to__layout_builder \
  paragraph_2_column_4_8_right__to__layout_builder \
  paragraph_2_column_8_4_left__to__layout_builder \
  paragraph_2_column_8_4_right__to__layout_builder \
  paragraph_sacnas_officer_body_text__to__layout_builder \
  paragraph_lp_picbox_grid__to__layout_builder \
  paragraph_lp_vertical_tabs__to__layout_builder \
  paragraph_lp_adjustable_columns__to__layout_builder \
; do run "$m" "$m"; done

# --- Section 4: nodes ---
for m in \
  cas_150_species_to_150_species \
  cas_aaa_to_aaa \
  cas_course_to_course \
  cas_dfs_to_dfs \
  cas_dfsg_to_dfsg \
  cas_enterprise_budgets_to_enterprise_budgets \
  cas_fun_facts_to_fun_facts \
  cas_funding_opportunities_to_funding_opportunities \
  cas_image_album_to_image_album \
  cas_project_to_project \
  cas_pvr_to_pvr \
  cas_video_to_video \
  cas_weed_to_weed \
  cas_weather_data_to_weather_daily_data \
  cas_weather_daily_data_to_weather_daily_data \
  cas_weather_monthly_data_to_weather_monthly_data \
  upgrade_d7_biblio_publication \
; do run "$m" "$m"; done

# --- Section 5: pages + stories ---
for m in \
  cas_book_to_page \
  cas_page_to_page \
  cas_feature_page_to_page \
  cas_paragraph_page_to_page \
  cas_feature_story_to_story \
  cas_story_to_story \
  cas_article_to_story \
; do run "$m" "$m"; done

# --- Section 6: groups ---
run "upgrade_d7_view_modes"           upgrade_d7_view_modes
run "upgrade_d7_node_og_group"        upgrade_d7_node_og_group
run "upgrade_d7_node_parent_unit_group" upgrade_d7_node_parent_unit_group
run "cas_node_parent_unit_group"      cas_node_parent_unit_group
run "cas_node_og_group"               cas_node_og_group
run "upgrade_d7_user_og_memberships"  upgrade_d7_user_og_memberships
run "upgrade_d7_node_og_organization" upgrade_d7_node_og_organization
run "cas_og_menu_group_menu"          cas_og_menu_group_menu
run "tag:CAS Groups"                  --tag='CAS Groups' --force
run "upgrade_d7_book_menu_group_menu" upgrade_d7_book_menu_group_menu

# --- Section 7: aliases + redirects + profiles + menus + blocks ---
run "tag:OSU Alias"                   --tag='OSU Alias'
run "tag:OSU Redirect"                --tag='OSU Redirect'
run "tag:OSU Drupal Profile"          --tag='OSU Drupal Profile'
run "cas_user_profile_osu_person"     cas_user_profile_osu_person
run "cas_user_profile_osu_employee"   cas_user_profile_osu_employee
run "cas_user_profile_osu_faculty"    cas_user_profile_osu_faculty
run "cas_user_profile_osu_student"    cas_user_profile_osu_student
run "cas_user_profile_agricultural_sciences" cas_user_profile_agricultural_sciences
run "tag:OSU Menus"                   --tag='OSU Menus'
run "tag:OSU Blocks"                  --tag='OSU Blocks'

echo ""
echo "===== Rebuilding cache ====="
ddev drush cr

# --- Verification: run the same post-rebuild checks rebuild_site.sh uses.
# verify_migration.sh exits 0 = PASS, 1 = FAIL, 2 = no verdict (script error).
echo ""
echo "===== Verification ====="
bash "${PROJECT_ROOT}/scripts-dev/verify_migration.sh"
VERIFY_RC=$?

echo ""
echo "============================================"
if [ ${#FAILED[@]} -eq 0 ]; then
  echo "MIGRATIONS: all commands completed (no command-level failures)"
else
  echo "MIGRATIONS: completed with ${#FAILED[@]} FAILED COMMAND(S):"
  printf '  - %s\n' "${FAILED[@]}"
fi
case "${VERIFY_RC}" in
  0) echo "VERIFICATION: PASS" ;;
  1) echo "VERIFICATION: FAIL (see checks above)" ;;
  *) echo "VERIFICATION: ERROR (no verdict — see output above)" ;;
esac
echo "============================================"

# Non-zero overall exit if anything failed, so callers/CI can detect it.
if [ ${#FAILED[@]} -ne 0 ] || [ "${VERIFY_RC}" -ne 0 ]; then
  exit 1
fi
