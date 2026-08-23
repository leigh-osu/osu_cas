<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\FunctionalJavascript\View;

use Drupal\Tests\entityreference_filter\FunctionalJavascript\EntityReferenceFunctionalJavascriptTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a multi-level dependent filter cascade (diamond A->{B,C}->D).
 *
 * Uses the page_5 display of test_view: D depends on both B and C via
 * "[b]+[c]", so their values are joined with '+' into a single contextual
 * argument. The whole cascade runs server-side in one request, so it cannot
 * recurse.
 *
 * @group entityreference_filter
 */
#[RunTestsInSeparateProcesses]
class EntityReferenceFilterCascadeJsTest extends EntityReferenceFunctionalJavascriptTestBase {

  /**
   * Tests the two-level cascade and the '+'-joined argument.
   */
  public function testCascadeDiamond(): void {
    $assert = $this->assertSession();
    $this->drupalGet('test-view-cascade');

    $a = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $b = 'edit-field-taxonomy-reference-target-id-entityreference-filter-1';
    $c = 'edit-field-taxonomy-reference-target-id-entityreference-filter-2';
    $d = 'edit-field-taxonomy-reference-target-id-entityreference-filter-3';

    // A is unset ('All'), so B/C/D show every term initially.
    $assert->optionExists($a, '1');
    $assert->optionExists($d, '1');
    $assert->optionExists($d, '4');

    // Choosing B=2 cascades to D: [b]+[c] = "2" (c is still 'All').
    $assert->selectExists($b)->selectOption('2');
    $assert->assertWaitOnAjaxRequest();
    $assert->optionExists($d, '2');
    $assert->optionNotExists($d, '1');

    // Choosing C=3 cascades to D: [b]+[c] = "2+3" -> D shows 2 AND 3.
    $assert->selectExists($c)->selectOption('3');
    $assert->assertWaitOnAjaxRequest();
    $assert->optionExists($d, '2');
    $assert->optionExists($d, '3');
    $assert->optionNotExists($d, '1');

    // Two-level cascade in one request: changing A rebuilds B, C and D.
    // A=1 narrows B and C to {1}; their previous values (2, 3) become invalid
    // and reset, which propagates to D.
    $assert->selectExists($a)->selectOption('1');
    $assert->assertWaitOnAjaxRequest();
    $assert->optionExists($b, '1');
    $assert->optionNotExists($b, '2');
    $assert->optionExists($c, '1');
    $assert->optionNotExists($c, '3');
  }

}
