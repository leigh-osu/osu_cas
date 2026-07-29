<?php

namespace Drupal\paragraphs_to_layout_builder\Plugin\migrate\process;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Utility\UrlHelper;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\paragraphs_to_layout_builder\LayoutBase;

/**
 * Custom plugin for handling paragraph menu items from d7.
 */
#[MigrateProcess(
  id: 'menu_item',
  handle_multiples: TRUE
)]
class MenuItem extends LayoutBase {

  /**
   * FontAwesome 3 class names whose modern equivalent is not "fas fa-<name>".
   *
   * Any other legacy "icon-<name>" token falls back to "fas fa-<name>", which
   * is correct for most of the remainder (icon-book, icon-leaf, icon-user...).
   */
  protected const LEGACY_ICON_MAP = [
    'icon-facebook-sign' => 'fab fa-facebook-square',
    'icon-facebook' => 'fab fa-facebook-f',
    'icon-twitter-sign' => 'fab fa-twitter-square',
    'icon-twitter' => 'fab fa-twitter',
    'icon-instagram' => 'fab fa-instagram',
    'icon-youtube' => 'fab fa-youtube',
    'icon-linkedin-sign' => 'fab fa-linkedin',
    'icon-linkedin' => 'fab fa-linkedin-in',
    'icon-google-plus-sign' => 'fab fa-google-plus-square',
    'icon-group' => 'fas fa-users',
    'icon-money' => 'far fa-money-bill-alt',
    'icon-dollar' => 'fas fa-dollar-sign',
    'icon-usd' => 'fas fa-dollar-sign',
    'icon-beaker' => 'fas fa-flask',
    'icon-check' => 'far fa-check-square',
    'icon-ok' => 'fas fa-check',
    'icon-calendar' => 'fas fa-calendar-alt',
    'icon-map-marker' => 'fas fa-map-marker-alt',
    'icon-question-sign' => 'fas fa-question-circle',
    'icon-edit-sign' => 'fas fa-edit',
    'icon-pencil' => 'fas fa-pencil-alt',
    'icon-file-alt' => 'far fa-file',
    'icon-file-text-alt' => 'far fa-file-alt',
    'icon-list-alt' => 'far fa-list-alt',
    'icon-bar-chart' => 'fas fa-chart-bar',
    'icon-signin' => 'fas fa-sign-in-alt',
    'icon-thumbs-up-alt' => 'far fa-thumbs-up',
    'icon-warning-sign' => 'fas fa-exclamation-triangle',
    'icon-exclamation-sign' => 'fas fa-exclamation-circle',
    'icon-info-sign' => 'fas fa-info-circle',
    'icon-eye-open' => 'far fa-eye',
    'icon-envelope-alt' => 'far fa-envelope',
    'icon-phone-sign' => 'fas fa-phone-square',
    'icon-external-link' => 'fas fa-external-link-alt',
    'icon-dashboard' => 'fas fa-tachometer-alt',
    'icon-smile' => 'far fa-smile',
    'icon-lightbulb' => 'far fa-lightbulb',
    'icon-legal' => 'fas fa-gavel',
    'icon-reorder' => 'fas fa-bars',
    'icon-facetime-video' => 'fas fa-video',
    'icon-food' => 'fas fa-utensils',
    'icon-star-empty' => 'far fa-star',
    'icon-glass' => 'fas fa-glass-martini',
    'icon-play-sign' => 'far fa-play-circle',
    'icon-mail-reply-all' => 'fas fa-reply-all',
    'icon-picture' => 'far fa-image',
    'icon-ellipsis-vertical' => 'fas fa-ellipsis-v',
    'icon-compass' => 'far fa-compass',
    'icon-information' => 'fas fa-info-circle',
  ];

  /**
   * Maps legacy FontAwesome 3 icon classes to modern equivalents.
   *
   * Old D7 menu paragraphs predate FontAwesome 4; values like
   * "icon-facebook-sign" have no CSS in the D10 themes, which load modern
   * FontAwesome plus the icomoon OSU brand font (icon-osu-*). Values already
   * using modern FontAwesome classes or icon-osu-* pass through untouched.
   *
   * @param string|null $value
   *   The raw D7 field_p_menu_icon value.
   *
   * @return string
   *   The icon classes to store on the D10 block.
   */
  protected function mapLegacyIcon(?string $value): string {
    if ($value === NULL) {
      return '';
    }
    // Strip stray HTML entity notes, e.g. "icon-list (&#xf03a;)".
    $icon = preg_replace('/\(?&#x?[0-9a-f]+;?\)?/i', ' ', $value);
    // Normalize editor typos like "icon_twitter".
    $icon = preg_replace('/\bicon_/', 'icon-', $icon);
    $icon = trim(preg_replace('/\s+/', ' ', $icon));
    if ($icon === '') {
      return '';
    }
    $tokens = explode(' ', $icon);
    foreach ($tokens as $token) {
      // icomoon OSU brand icons and modern FontAwesome classes are current.
      if (str_starts_with($token, 'icon-osu-')
        || str_starts_with($token, 'fa-')
        || in_array($token, ['fa', 'fas', 'far', 'fab', 'fal', 'fad'], TRUE)) {
        return $icon;
      }
    }
    // Legacy FontAwesome 3 classes: map each icon-* token, keep any other
    // token (e.g. "white").
    $mapped = [];
    foreach ($tokens as $token) {
      if (str_starts_with($token, 'icon-')) {
        $mapped[] = self::LEGACY_ICON_MAP[$token] ?? ('fas fa-' . substr($token, 5));
      }
      else {
        $mapped[] = $token;
      }
    }
    return implode(' ', $mapped);
  }

  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Get entity ids for link data.
    $entity_ids = [];
    foreach ($value as $entity_id) {
      $entity_ids[] = $entity_id['value'];
    }
    $results = [];
    // Some people have empty menu paragraphs.
    if (!empty($entity_ids)) {
      // Query migrateDb for link and icon data.
      $query = $this->migrateDb->select('field_data_field_p_menu_link', 'p');
      $query->fields('p', ['field_p_menu_link_title', 'field_p_menu_link_url']);
      $query->leftJoin('field_data_field_p_menu_icon', 'i', 'p.entity_id = i.entity_id');
      $query->fields('i', ['field_p_menu_icon_value']);
      $query->condition('p.entity_id', $entity_ids, 'IN');
      $query->orderBy('p.entity_id');
      $results = $query->execute();
    }
    // Use query results to build menu bar item blocks, save block ids for later
    // use.
    $block_ids = [];
    foreach ($results as $result) {
      // Check for valid urls. Make changes as necessary.
      $url = $result->field_p_menu_link_url;
      if (UrlHelper::isValid($url)) {
        if (!(str_starts_with($url, 'http') || str_starts_with($url, 'mailto'))) {
          if (str_starts_with($url, '/')) {
            $url = 'internal:' . $url;
          }
          else {
            $url = 'internal:/' . $url;
          }
        }
      }
      else {
        // Clean out invalid URLs.
        $url = '';
      }
      $block = BlockContent::create([
        'info' => 'Migrated d7 paragraph paragraph_menu',
        'type' => 'osu_menu_bar_item',
        'langcode' => 'en',
        'field_osu_menu_bar_item_link' => [
          'uri' => $url,
          'title' => $result->field_p_menu_link_title,
        ],
        'field_osu_menu_bar_item_icon' => $this->mapLegacyIcon($result->field_p_menu_icon_value),
        'reusable' => 0,
      ]);
      $block->save();
      $block_ids[] = $block->id();
    }

    return implode(',', $block_ids);
  }

}
