<?php

namespace Drupal\osu_endophyte\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Sample workflow.
 */
class SampleStatusConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {

    if (!isset($entity)) {
      return;
    }

    // If the entity already has an id we're in an entity *update* operation
    // instead of an entity *creation* operation. We don't want prevent
    // existing articles from being updated.
//    if ($entity->id()) {
//      return;
//    }
//
//    if ($entity->bundle() == 'sample') {
////      dsm($entity);
////      if (!$entity->field_sample_notes->value){
////        $this->context->addViolation($constraint->empty);
//      }

      //      // Find out how many articles have already been created.
//      $sample_count = \Drupal::entityQuery('node')
//        ->condition('type', 'sample')
//        ->count()
//        ->execute();
//
//      if ($sample_count >= $limit) {
//        $this->context->addViolation($constraint->message);
//      }
    }



//    foreach ($items as $item) {
//      // First check if the value is an integer.
//      if (!is_int($item->value)) {
//        // The value is not an integer, so a violation, aka error, is applied.
//        // The type of violation applied comes from the constraint description
//        // in step 1.
//        $this->context->addViolation($constraint->notInteger, ['%value' => $item->value]);
//      }
//
//      // Next check if the value is unique.
//      if (!$this->isUnique($item->value)) {
//        $this->context->addViolation($constraint->notUnique, ['%value' => $item->value]);
//      }
//    }
//  }

//  /**
//   * Is unique?
//   *
//   * @param string $value
//   */
//  private function isUnique($value)
//  {
//    // Here is where the check for a unique value would happen.
//  }

}
