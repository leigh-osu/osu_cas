<?php

namespace Drupal\osu_endophyte\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the Sample has at least one attached Test.
 *
 * @Constraint(
 *   id = "SampleTest",
 *   label = @Translation("SampleTest", context = "Validation"),
 *   type = "string"
 * )
 */
class SampleTestConstraint extends Constraint {

  // The message that will be shown if there is not a test selected.
  public $noTest = 'A Sample must have at least one test selected before it can be entered.';

}
