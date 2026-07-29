<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display whether the alert is scheduled.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("sitewide_alert_scheduled")]
class SitewideAlertScheduled extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $value = $this->getValue($values);
    return $value ? '✔' : '✘';
  }

}
