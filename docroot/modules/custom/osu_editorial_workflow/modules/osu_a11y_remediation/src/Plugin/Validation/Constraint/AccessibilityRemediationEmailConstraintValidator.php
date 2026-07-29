<?php

namespace Drupal\osu_a11y_remediation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the accessibility remediation email requirement for a node.
 *
 * Ensures that if the moderation state of the node is not set to 'preserve',
 * the 'osu_a11y_remediation_email' field must not be empty. If this condition
 * is not met, a constraint violation is added to the relevant field.
 *
 * This validator is typically used in the context of Drupal nodes to enforce
 * certain accessibility-related rules.
 */
final class AccessibilityRemediationEmailConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritDoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (empty($value)) {
      return;
    }
    /** @var \Drupal\node\NodeInterface $node */
    $node = $value;
    assert($constraint instanceof AccessibilityRemediationEmailConstraint);
    $moderationState = $node->get('moderation_state')->getString();
    // Check if the moderation state is set to 'preserve'.
    if ($moderationState === 'preserve') {
      if ($node->get('osu_a11y_remediation_email')->isEmpty()) {
        $this->context->buildViolation($constraint->message)
          ->atPath('osu_a11y_remediation_email')
          ->addViolation();
      }
    }
  }

}
