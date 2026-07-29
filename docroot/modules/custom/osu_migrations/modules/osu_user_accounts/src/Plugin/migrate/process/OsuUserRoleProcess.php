<?php

namespace Drupal\osu_user_accounts\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Convert the old roles into the new ones.
 *
 * Get the role names from Drupal 7 and map them to our roles in New Drupal.
 * We cannot rely on role ID's being constant in Drupal 7 so we have to do this
 *  to map roles.
 *
 * @code
 * process:
 *   plugin: osu_user_role_map
 * @endcode
 */
#[MigrateProcess(
  id: 'osu_user_role_map'
)]
class OsuUserRoleProcess extends ProcessPluginBase {

  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $rid = $value;
    // Get the migrate source database connection.
    $database = Database::getConnection('default', 'migrate');
    $result = $database->query('SELECT [name] FROM {role} WHERE [rid] = :rid', [':rid' => $rid])
      ->fetchAssoc();
    // Loop over each name and map the roles.
    foreach ($result as $role_name) {
      switch ($role_name) {
        case 'earl':
          return 'earl';

        case 'architect':
          return 'architect';

        case 'author':
          return 'content_authors';

        case 'manager':
          return 'manage_site_configuration';

        case 'group user':
          return 'group_content_author';
      }
    }
  }

}
