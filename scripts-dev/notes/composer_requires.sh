#! /bin/bash

ddev composer require 'drupal/domain:^3.0@beta'
ddev composer require 'drupal/multiselect:^2.0@beta'

ddev composer require 'drupal/entity_clone:^2.1@beta'
ddev composer require 'drush/drush:^13.6'

ddev composer require 'drupal/migrate_devel:^3.0' --dev
ddev composer require 'drupal/devel_debug_log:^2.0' --dev

ddev composer require 'drupal/layout_builder_reorder:^2.0'

composer config repositories.repo-name vcs https://github.com/<orgname-or-username>/<repo-name>.git
