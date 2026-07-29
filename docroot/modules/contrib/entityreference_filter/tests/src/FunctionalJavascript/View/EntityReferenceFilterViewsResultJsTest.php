<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\FunctionalJavascript\Views;

use Drupal\Tests\entityreference_filter\FunctionalJavascript\EntityReferenceFunctionalJavascriptTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entityreference filter behavior in views.
 *
 * @group entityreference_filter
 */
#[RunTestsInSeparateProcesses]
class EntityReferenceFilterViewsResultJsTest extends EntityReferenceFunctionalJavascriptTestBase {

  /**
   * Tests filter options with no arguments.
   */
  public function testFilterOptionsWithoutArguments() {
    $field_id_controlling = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $field_id_dependent = 'edit-field-taxonomy-reference-target-id-entityreference-filter-1';

    $this->drupalGet('test-view-arg-exposed-filter');

    // Controlling field.
    $this->assertSession()->selectExists($field_id_controlling);
    $this->assertSession()->optionExists($field_id_controlling, 'All');
    $this->assertSession()->optionExists($field_id_controlling, '1');
    $this->assertSession()->optionExists($field_id_controlling, '2');
    $this->assertSession()->optionExists($field_id_controlling, '3');
    $this->assertSession()->optionExists($field_id_controlling, '4');
    $this->assertSession()->optionNotExists($field_id_dependent, '5');
    $options = $this->assertSession()->selectExists($field_id_controlling)->findAll('xpath', '//option');
    $this->assertCount(5, $options);

    // Dependent field.
    $this->assertSession()->selectExists($field_id_dependent);
    $this->assertSession()->optionExists($field_id_dependent, 'All');
    $this->assertSession()->optionExists($field_id_dependent, '1');
    $this->assertSession()->optionExists($field_id_dependent, '2');
    $this->assertSession()->optionExists($field_id_dependent, '3');
    $this->assertSession()->optionExists($field_id_dependent, '4');
    $this->assertSession()->optionNotExists($field_id_dependent, '5');
    $options = $this->assertSession()->selectExists($field_id_dependent)->findAll('xpath', '//option');
    $this->assertCount(5, $options);

    // Select value equal `1`.
    $this->assertSession()->selectExists($field_id_controlling)->selectOption('1');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->optionExists($field_id_dependent, 'All');
    $this->assertSession()->optionExists($field_id_dependent, '1');
    $this->assertSession()->optionNotExists($field_id_dependent, '2');
    $this->assertCount(2, $this->assertSession()->selectExists($field_id_dependent)->findAll('xpath', '//option'));

    // Select value equal `2`.
    $this->assertSession()->selectExists($field_id_controlling)->selectOption('2');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->optionExists($field_id_dependent, 'All');
    $this->assertSession()->optionExists($field_id_dependent, '2');
    $this->assertSession()->optionNotExists($field_id_dependent, '1');
    $this->assertCount(2, $this->assertSession()->selectExists($field_id_dependent)->findAll('xpath', '//option'));

    // Select value equal `All`.
    $this->assertSession()->selectExists($field_id_controlling)->selectOption('All');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->optionExists($field_id_dependent, 'All');
    $this->assertSession()->optionExists($field_id_dependent, '4');
    $this->assertSession()->optionNotExists($field_id_dependent, '5');
    $this->assertCount(5, $this->assertSession()->selectExists($field_id_dependent)->findAll('xpath', '//option'));
  }

}
