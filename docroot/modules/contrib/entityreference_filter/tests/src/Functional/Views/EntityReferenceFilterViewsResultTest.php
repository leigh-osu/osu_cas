<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Functional\Views;

use Drupal\Tests\entityreference_filter\Functional\EntityReferenceFunctionalTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entityreference filter behavior in views.
 *
 * @group entityreference_filter
 */
#[RunTestsInSeparateProcesses]
class EntityReferenceFilterViewsResultTest extends EntityReferenceFunctionalTestBase {

  /**
   * Tests filter options with no arguments.
   */
  public function testFilterOptionsWithoutArguments() {
    $this->drupalGet('test-view-no-args');
    $this->assertSession()->statusCodeEquals(200);

    $field_id = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $this->assertSession()->selectExists($field_id);
    $this->assertSession()->optionExists($field_id, 'All');
    $this->assertSession()->optionExists($field_id, '1');
    $this->assertSession()->optionExists($field_id, '2');
    $this->assertSession()->optionExists($field_id, '3');
    $this->assertSession()->optionExists($field_id, '4');
    $this->assertSession()->optionNotExists($field_id, '5');
    $select = $this->assertSession()->selectExists($field_id);
    $this->assertCount(5, $select->findAll('xpath', '//option'));
  }

  /**
   * Tests filter options with url arguments.
   */
  public function testFilterOptionsWithUrlArguments() {
    $url_1 = 'test-view-arg-url/1';
    $url_2 = 'test-view-arg-url/5';
    $this->dynamicFilterOptionsWithArgumentsTest($url_1, $url_2);
  }

  /**
   * Tests filter options with context arguments.
   */
  public function testFilterOptionsWithContextualArguments() {
    $url_1 = 'test-view-arg-contextual/1';
    $url_2 = 'test-view-arg-contextual/5';
    $this->dynamicFilterOptionsWithArgumentsTest($url_1, $url_2);
  }

  /**
   * Common test method.
   *
   * @param string $url_1
   *   URL 1 to visit.
   * @param string $url_2
   *   URL 2 to visit.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function dynamicFilterOptionsWithArgumentsTest($url_1, $url_2) {
    $this->drupalGet($url_1);
    $this->assertSession()->statusCodeEquals(200);

    // 2 options are presented: `1` and `All`.
    $field_id = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $this->assertSession()->selectExists($field_id);
    $this->assertSession()->optionExists($field_id, 'All');
    $this->assertSession()->optionExists($field_id, '1');
    $select = $this->assertSession()->selectExists($field_id);
    $this->assertCount(2, $select->findAll('xpath', '//option'));

    // 1 option is presented: `All` and the form field is hidden.
    $this->drupalGet($url_2);
    $this->assertSession()->statusCodeEquals(200);
    $field_id = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $this->assertSession()->selectExists($field_id);
    $elements = $this->cssSelect('.hidden select#edit-field-taxonomy-reference-target-id-entityreference-filter');
    $this->assertEquals(1, count($elements));
    $this->assertSession()->optionExists($field_id, 'All');
  }

}
