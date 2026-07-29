<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueInteger constraint.
 */
class ScheduledDateProvidedConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if ($value->isScheduled() &&
      ($value->getScheduledStartDateTime() === NULL ||
        $value->getScheduledEndDateTime() === NULL)) {
      $this->context->buildViolation($constraint->messageDatesNotProvided)
        ->atPath('scheduled_date')
        ->addViolation();
    }
  }

}
