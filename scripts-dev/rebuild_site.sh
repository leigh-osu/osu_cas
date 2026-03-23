#!/bin/bash

# Drupal Multisite Rebuild Script for Migration Testing
# This script rebuilds a fresh Drupal site from scratch

# set -e  # Exit on any error

# echo "=== Rebuilding Drupal site for migration testing ==="

# # Configuration
# SITE_NAME="College of Agricultural Sciences"
# ADMIN_USER="cws_dpla"
# ADMIN_PASS="ok"  
# ADMIN_EMAIL="noreply@mail.drupal.oregonstate.edu"
# PROFILE="osu_standard" 
# SITE_URI="agsci.oregonstate.edu"  # For multisite (sites/ directory uses dots)
# CONFIG_DIR="agsci-oregonstate-edu"  # Config directory uses dashes

# # Stop and restart DDEV to reset database
# echo "Resetting DDEV environment..."
# ddev stop -O -R
# ddev start

# # Install Drupal
# echo "Installing Drupal with ${PROFILE} profile..."

# ddev drush site:install ${PROFILE} \
#   --site-name="${SITE_NAME}" \
#   --account-name="${ADMIN_USER}" \
#   --account-pass="${ADMIN_PASS}" \
#   --account-mail="${ADMIN_EMAIL}" \
#   --sites-subdir="${SITE_URI}" \
#   --yes
    
# # ddev import-db --file="/Users/leighr/Sites/osu/osu_cas/scripts-dev/agsci_oregonstate_edu_1772688701.sql"

# # Install modules
# echo "Installing modules..."
    
# ddev drush en domain domain_access domain_alias domain_content multiselect layout_builder_modal -y
# ddev drush en migrate migrate_drupal phpass migrate_plus -y
# ddev drush en osu_migrations osu_user_accounts osu_migrations_files osu_migrations_media osu_migrations_taxonomy \
#     osu_migrate_content og_to_group paragraphs_to_layout_builder osu_user_to_profiles devel migrate_devel \
#     domain_migrate domain_access_migrate -y

# ddev drush cr -y

# ddev drush theme:install manzanita -y

# ddev drush config:set system.theme default manzanita -y

# ddev drush config:set -y system.performance css.preprocess 0
# ddev drush config:set -y system.performance js.preprocess 0
# ddev drush config:set -y system.performance cache.page.max_age 0


# ddev drush cr -y
# ddev drush ev "node_access_rebuild();"

# # end install


# ddev drush migrate:import --tag='OSU Accounts'
# ddev drush migrate:import --tag='OSU Media' 

# ddev snapshot -n aftermedia

# ddev snapshot restore aftermedia

# ddev drush migrate:import d7_image_styles
# ddev drush migrate:import --tag='OSU Taxonomy'

# ddev drush migrate:import --tag='OSU Custom Blocks' --force

# ddev snapshot -n afterosublocks

ddev snapshot restore afterosublocks

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

ddev drush en osu_migrations_cas -y
ddev drush migrate:import d7_domain

# ddev drush migrate:import field_collection_field_lp_adj_column__to__layout_builder
# ddev drush migrate:import field_collection_field_lp_picbox__to__layout_builder
# ddev drush migrate:import paragraph_1_col_clean__to__layout_builder
# ddev drush migrate:import paragraph_1_column_full_width__to__layout_builder
# ddev drush migrate:import paragraph_3_col_center__to__layout_builder
# ddev drush migrate:import paragraph_3_col_left__to__layout_builder
# ddev drush migrate:import paragraph_3_col_right__to__layout_builder
# ddev drush migrate:import paragraph_4_column_col1__to__layout_builder
# ddev drush migrate:import paragraph_4_column_col2__to__layout_builder
# ddev drush migrate:import paragraph_4_column_col3__to__layout_builder
# ddev drush migrate:import paragraph_4_column_col4__to__layout_builder
# ddev drush migrate:import paragraph_accordian__to__layout_builder
# ddev drush migrate:import paragraph_accordion__to__layout_builder
# ddev drush migrate:import paragraph_divider__to__layout_builder
# ddev drush migrate:import paragraph_menu__to__layout_builder
# ddev drush migrate:import paragraph_1_col__to__layout_builder
# ddev drush migrate:import paragraph_2_col_left__to__layout_builder
# ddev drush migrate:import paragraph_2_col_right__to__layout_builder
# ddev drush migrate:import paragraph_2_column_4_8_left__to__layout_builder
# ddev drush migrate:import paragraph_2_column_4_8_right__to__layout_builder
# ddev drush migrate:import paragraph_2_column_8_4_left__to__layout_builder
# ddev drush migrate:import paragraph_2_column_8_4_right__to__layout_builder
# ddev drush migrate:import paragraph_sacnas_officer_body_text__to__layout_builder
# ddev drush migrate:import paragraph_lp_picbox_grid__to__layout_builder
# ddev drush migrate:import paragraph_lp_vertical_tabs__to__layout_builder


# ddev drush migrate:import --tag='CAS Paragraphs' --force
# ddev drush migrate:import --tag='OSU Paragraphs' --force

# ddev drush ms --tag='CAS Paragraphs'
# ddev drush ms --tag='OSU Paragraphs'

# ddev drush migrate:import cas_page_to_page --force
# ddev drush migrate:import cas_book_to_page --force
# ddev drush migrate:import cas_paragraph_page_to_page --force


# ddev drush migrate:import --tag='Layout content' --force
# ddev drush migrate:import --tag='Feature Story'
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force

# ddev drush migrate:import --tag='OSU Content' --force
# ddev drush migrate:import --tag='OSU Content' --force
# ddev drush migrate:import --tag='OSU Content' --force
# ddev drush migrate:import --tag='OSU Content' --force
# ddev drush migrate:import --tag='OSU Groups' --force
# ddev drush migrate:import --tag='OSU Alias' 
# ddev drush migrate:import --tag='OSU Redirect'
# ddev drush migrate:import --tag='OSU Drupal Profile'
# ddev drush migrate:import --tag='OSU Menus'
# ddev drush migrate:import --tag='OSU Blocks'

# ddev xdebug

echo ""
echo "=== Site rebuild complete! ==="
ddev drush uli

