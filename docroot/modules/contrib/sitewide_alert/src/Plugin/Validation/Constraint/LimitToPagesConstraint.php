<?php

namespace Drupal\sitewide_alert\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint;

/**
 * Plugin implementation of the 'LimitToPages'.
 */
#[\Drupal\Core\Validation\Attribute\Constraint(
  id: 'LimitToPages',
  label: new TranslatableMarkup('Limit to pages constraint', [], ['context' => 'Validation']),
  type: ['entity', 'sitewide_alert']
)]
class LimitToPagesConstraint extends Constraint {

  /**
   * Message for invalid paths.
   *
   * Message shown when the entity is marked to limit to specific pages, but
   * what is giving is invalid.
   *
   * @var string
   */
  public string $messageInvalidPaths = 'One or more of the limit by page path(s) are invalid. Please provide a leading slash followed by the page URI. One per line.';

}
