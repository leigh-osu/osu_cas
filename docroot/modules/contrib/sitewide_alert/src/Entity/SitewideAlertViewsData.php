<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Sitewide Alert entities.
 */
class SitewideAlertViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    $data['sitewide_alert_field_data']['scheduled_date__value']['filter'] = [
      'title' => $this->t('Scheduled Date'),
      'field' => 'scheduled_date__value',
      'table' => 'sitewide_alert_field_data',
      'id' => 'datetime',
      'field_name' => 'scheduled_date',
      'entity_type' => 'sitewide_alert',
      'allow empty' => TRUE,
    ];

    $data['sitewide_alert_field_data']['scheduled_date__end_value']['filter'] = [
      'title' => $this->t('Scheduled Date (end_value)'),
      'field' => 'scheduled_date__end_value',
      'table' => 'sitewide_alert_field_data',
      'id' => 'datetime',
      'field_name' => 'scheduled_date',
      'entity_type' => 'sitewide_alert',
      'allow empty' => TRUE,
    ];

    // Custom field: Style with human-readable label.
    $data['sitewide_alert_field_data']['style_label'] = [
      'title' => $this->t('Style (label)'),
      'help' => $this->t('The style of the alert with human-readable label.'),
      'field' => [
        'id' => 'sitewide_alert_style',
        'field' => 'style',
        'click sortable' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
        'field' => 'style',
      ],
      'filter' => [
        'id' => 'string',
        'field' => 'style',
      ],
    ];

    // Custom field: Active status with checkmark display.
    $data['sitewide_alert_field_data']['status_indicator'] = [
      'title' => $this->t('Active (indicator)'),
      'help' => $this->t('Shows a checkmark if the alert is active (published).'),
      'field' => [
        'id' => 'sitewide_alert_active',
        'field' => 'status',
        'click sortable' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
        'field' => 'status',
      ],
      'filter' => [
        'id' => 'boolean',
        'field' => 'status',
        'label' => $this->t('Active'),
        'use_equal' => TRUE,
      ],
    ];

    // Custom field: Scheduled status with checkmark display.
    $data['sitewide_alert_field_data']['scheduled_indicator'] = [
      'title' => $this->t('Scheduled (indicator)'),
      'help' => $this->t('Shows a checkmark if the alert is scheduled.'),
      'field' => [
        'id' => 'sitewide_alert_scheduled',
        'field' => 'scheduled_alert',
        'click sortable' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
        'field' => 'scheduled_alert',
      ],
      'filter' => [
        'id' => 'boolean',
        'field' => 'scheduled_alert',
        'label' => $this->t('Scheduled'),
        'use_equal' => TRUE,
      ],
    ];

    // Custom field: Page visibility with complex rendering.
    // This is a computed field that loads the entity, not a database column.
    $data['sitewide_alert']['page_visibility'] = [
      'title' => $this->t('Page visibility'),
      'help' => $this->t('Shows the pages where the alert is visible.'),
      'field' => [
        'id' => 'sitewide_alert_page_visibility',
      ],
    ];

    return $data;
  }

}
