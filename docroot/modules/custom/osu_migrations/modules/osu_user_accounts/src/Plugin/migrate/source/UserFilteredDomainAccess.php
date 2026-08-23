<?php

namespace Drupal\osu_user_accounts\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\osu_user_accounts\Plugin\migrate\source\UserFiltered;

/**
 * Filtered D7 user source with domain access fields.
 *
 * Combines role-based filtering (d7_user_filtered) with domain access
 * data from the D7 domain_editor table.
 *
 * @MigrateSource(
 *   id = "d7_user_filtered_domain_access",
 *   source_module = "user"
 * )
 */
class UserFilteredDomainAccess extends UserFiltered {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['domain_access_user'] = $this->t('User Domain Access');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $row->setSourceProperty('domain_access_user', $this->getDomainTargetIds($row->getSourceProperty('uid')));
    return parent::prepareRow($row);
  }

  /**
   * Returns domain machine names as target_id arrays for the given uid.
   *
   * @param int $uid
   *   User ID from the D7 source.
   *
   * @return array
   *   Array of ['target_id' => machine_name] entries.
   */
  private function getDomainTargetIds(int $uid): array {
    $result = [];

    $domain_ids = $this->select('domain_editor', 'de')
      ->fields('de', ['domain_id'])
      ->condition('de.uid', $uid)
      ->execute()
      ->fetchCol();

    foreach ($domain_ids as $domain_id) {
      $machine_names = $this->select('domain', 'da')
        ->fields('da', ['machine_name'])
        ->condition('da.domain_id', $domain_id)
        ->execute()
        ->fetchCol();
      if (!empty($machine_names)) {
        $result[] = ['target_id' => $machine_names[0]];
      }
    }

    return $result;
  }

}
