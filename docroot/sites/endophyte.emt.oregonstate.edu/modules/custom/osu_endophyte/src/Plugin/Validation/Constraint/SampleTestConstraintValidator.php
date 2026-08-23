<?php

namespace Drupal\osu_endophyte\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SampleTest constraint.
 */
class SampleTestConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {

    if (!isset($entity) || $entity->bundle() != 'sample') {
      return;
    }

    if (!isset($entity->field_sample_test_ergovaline->entity) && !isset($entity->field_sample_test_lolitrem_b->entity) && !isset($entity->field_sample_test_pellet->entity) && !isset($entity->field_sample_test_ergot->entity)){
      $this->context->addViolation($constraint->noTest);
    }

//    foreach ($value as $item) {
//      // First check if the value is an integer.
//      if (!is_int($item->value)) {
//        // The value is not an integer, so a violation, aka error, is applied.
//        // The type of violation applied comes from the constraint description
//        // in step 1.
//        $this->context->addViolation($constraint->notInteger, ['%value' => $item->value]);
//      }
//    }
  }
}
