#! /bin/bash

ddev snapshot restore osu_profile

ddev drush en migrate migrate_drupal phpass migrate_plus
ddev drush en osu_migrations osu_user_accounts osu_migrations_files osu_migrations_media osu_migrations_taxonomy
ddev drush en osu_migrate_content og_to_group paragraphs_to_layout_builder osu_user_to_profiles
ddev drush en devel devel_debug_log migrate_devel
ddev drush en osu_migrations_cas 
ddev drush cim --partial --source=../localscripts/config -y

ddev drush migrate:import --tag='OSU Accounts'
ddev drush migrate:import --tag='OSU Media' 

# ddev snapshot -n aftermedia

# ddev snapshot restore aftermedia

ddev drush migrate:import d7_image_styles
ddev drush migrate:import --tag='OSU Taxonomy'

ddev drush migrate:import --tag='OSU Custom Blocks' --force

ddev drush migrate:import --tag='CAS Paragraphs' --force
ddev drush migrate:import --tag='OSU Paragraphs' --force

ddev drush migrate:import cas_page_to_page --force
ddev drush migrate:import cas_book_to_page --force
ddev drush migrate:import cas_paragraph_page_to_page --force

# ddev drush migrate:import --tag='Layout content' --force
# ddev drush migrate:import --tag='Feature Story'
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force
# ddev drush migrate:import --tag='OSU Configuration' --force

# ddev drush migrate:import d7_domain
# # ddev drush migrate:import upgrade_d7_node_domain_access

# ddev drush migrate:import --tag='OSU Content' --force
# ddev drush migrate:import --tag='OSU Content' --force
# ddev drush migrate:import --tag='OSU Content' --force
# ddev drush migrate:import --tag='OSU Content' --force
# # ddev drush migrate:import d7_views_migration
# ddev drush migrate:import --tag='OSU Groups' --force
# ddev drush migrate:import --tag='OSU Alias' 
# ddev drush migrate:import --tag='OSU Redirect'
# ddev drush migrate:import --tag='OSU Drupal Profile'
# ddev drush migrate:import --tag='OSU Menus'
# ddev drush migrate:import --tag='OSU Blocks'

# # ddev drush  config:delete block.block.bartik_system_main
# # ddev drush  config:delete block.block.seven_system_main

# ddev drush uli