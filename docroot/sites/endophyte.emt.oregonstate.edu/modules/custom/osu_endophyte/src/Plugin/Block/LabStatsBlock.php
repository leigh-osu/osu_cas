<?php

namespace Drupal\osu_endophyte\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\osu_endophyte\ESL\LabStats;


/**
 * Provides a Lab Statistics block.
 *
 * @Block(
 *   id = "osu_endophyte_lab_stats",
 *   admin_label = @Translation("Lab Statistics"),
 *   category = @Translation("Custom")
 * )
 */
class LabStatsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
//  protected function blockAccess(AccountInterface $account) {
//    // @DCG Evaluate the access condition here.
//    $condition = TRUE;
//    return AccessResult::allowedIf($condition);
//  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $LabStats = new LabStats();
    $stats = $LabStats->getStats();

    $html = "<h4>Tests requested</h4>";
    $html .= "<table class='labstats'>
                <tr class='ergovaline'><td>Ergovaline RUSH</td><td class='pl-1 text-right'>". $stats['ergovaline-rush-pending'] ."</td></tr>
                <tr class='ergovaline'><td>Ergovaline</td><td class='pl-1 text-right'>". $stats['ergovaline-pending'] ."</td></tr>
                <tr class='lolitrem-b'><td>Lolitrem B RUSH</td><td class='pl-1 text-right'>". $stats['lolitrem_b-rush-pending'] ."</td></tr>
                <tr class='lolitrem-b'><td>Lolitrem B</td><td class='pl-1 text-right'>". $stats['lolitrem_b-pending'] ."</td></tr>
                <tr class='pellet'><td>Pellet</td><td class='pl-1 text-right'>". $stats['pellet-pending'] ."</td></tr>
                <tr class='ergot'><td>Ergot</td><td class='pl-1 text-right'>". $stats['ergot-pending'] ."</td></tr>
              </table>";
    $html .= "<h4>Tests completed<br />(last 7 days)</h4>";
    $html .= "<table class='labstats'>
                <tr class='ergovaline'><td>Ergovaline RUSH</td><td class='pl-1 text-right'>". $stats['ergovaline-rush-complete'] ."</td></tr>
                <tr class='ergovaline'><td>Ergovaline</td><td class='pl-1 text-right'>". $stats['ergovaline-complete'] ."</td></tr>
                <tr class='lolitrem-b'><td>Lolitrem B RUSH</td><td class='pl-1 text-right'>". $stats['lolitrem_b-rush-complete'] ."</td></tr>
                <tr class='lolitrem-b'><td>Lolitrem B</td><td class='pl-1 text-right'>". $stats['lolitrem_b-complete'] ."</td></tr>
                <tr class='pellet'><td>Pellet</td><td class='pl-1 text-right'>". $stats['pellet-complete'] ."</td></tr>
                <tr class='ergot'><td>Ergot</td><td class='pl-1 text-right'>". $stats['ergot-complete'] ."</td></tr>
              </table>";

    $build['content'] = [
      '#markup' => $this->t($html),
      '#cache' => [
        'max-age' => 900,
      ],
      '#attached' => [
        'library' => [
          'osu_endophyte/labstatsblock',
        ],
      ],
    ];
    return $build;
  }

}
