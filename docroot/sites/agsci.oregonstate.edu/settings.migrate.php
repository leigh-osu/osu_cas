<?php

# migrate db source
$settings['migrate_source_connection'] = 'migrate';
$settings['migrate_source_version'] = '7';
# $settings['migrate_file_public_path'] = '';
# $settings['migrate_file_private_path'] = '';
$databases['migrate']['default']['database'] = "db";
$databases['migrate']['default']['username'] = "db";
$databases['migrate']['default']['password'] = "db";
$databases['migrate']['default']['host'] = "ddev-agscid7-db";
$databases['migrate']['default']['port'] = "3306";
$databases['migrate']['default']['driver'] = "mysql";

$settings['file_private_path'] = '../files/agsci/private-files';
