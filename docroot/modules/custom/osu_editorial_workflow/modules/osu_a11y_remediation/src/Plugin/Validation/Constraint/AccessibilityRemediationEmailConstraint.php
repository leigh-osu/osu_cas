<?php

namespace Drupal\osu_a11y_remediation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Constraint for accessibility remediation email.
 */
#[Constraint(
  id: 'AccessibilityRemediationEmail',
  label: new TranslatableMarkup('Accessibility Remediation Email', [], ['context' => 'Validation']),
  type: 'string',
)]
final class AccessibilityRemediationEmailConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $message = 'An accessibility remediation contact email is required when content is in the Preserve—Public Record Exception state.';

}
