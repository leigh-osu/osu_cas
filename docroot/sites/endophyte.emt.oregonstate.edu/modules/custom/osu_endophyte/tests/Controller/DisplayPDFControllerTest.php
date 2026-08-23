<?php

namespace Drupal\osu_endophyte\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the osu_endophyte module.
 */
class DisplayPDFControllerTest extends WebTestBase {


  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "osu_endophyte DisplayPDFController's controller functionality",
      'description' => 'Test Unit for module osu_endophyte and controller DisplayPDFController.',
      'group' => 'Other',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests osu_endophyte functionality.
   */
  public function testDisplayPDFController() {
    // Check that the basic functions of module osu_endophyte.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
