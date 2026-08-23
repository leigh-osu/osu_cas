<?php

namespace Drupal\Tests\pdf_using_mpdf\Functional;

/**
 * Functional tests class.
 *
 * @package Drupal\Tests\pdf_using_mpdf\Functional
 */
class PdfUsingMpdfOutputTest extends PdfUsingMpdfTestBase {

  /**
   * Tests if output is a PDF file.
   *
   * Only for the case when PDF is generated and displayed on the browser.
   */
  public function testOutput() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/people/permissions');
+   $this->submitForm(['authenticated[generate type_a pdf]' => TRUE], 'Save permissions');

    $node_type_a = $this->createNode(['type' => 'type_a']);
    $this->drupalGet('node/' . $node_type_a->id() . '/pdf');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'application/pdf');
  }

}
