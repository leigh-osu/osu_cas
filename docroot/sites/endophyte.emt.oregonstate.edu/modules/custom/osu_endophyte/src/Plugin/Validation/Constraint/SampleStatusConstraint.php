<?php

namespace Drupal\osu_endophyte\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that fields make sense for workflow.
 *
 * @Constraint(
 *   id = "SampleStatus",
 *   label = @Translation("Sample check", context = "Validation"),
 *   type = "entity"
 * )
 */
class SampleStatusConstraint extends Constraint {

  // The message that will be shown if the value is not an integer.
//  public $notInteger = '%value is not an integer';
//
//  // The message that will be shown if the value is not unique.
//  public $notUnique = '%value is not unique';
    public $message = "Stop in the name of love";

    public $empty = "Notes field can't be empty";
}
