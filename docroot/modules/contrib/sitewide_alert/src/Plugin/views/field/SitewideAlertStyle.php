<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert\Plugin\views\field;

use Drupal\sitewide_alert\AlertStyleProvider;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display the sitewide alert style label.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("sitewide_alert_style")]
class SitewideAlertStyle extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $value = $this->getValue($values);
    if ($value === NULL || $value === '') {
      return '';
    }
    return (string) AlertStyleProvider::alertStyleName($value);
  }

}
