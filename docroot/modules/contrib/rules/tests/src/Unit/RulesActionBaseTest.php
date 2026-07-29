<?php

declare(strict_types=1);

namespace Drupal\Tests\rules\Unit;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rules\Core\RulesActionBase;

/**
 * @coversDefaultClass \Drupal\rules\Core\RulesActionBase
 * @group Rules
 */
class RulesActionBaseTest extends RulesUnitTestBase {

  /**
   * Tests that a missing label throws an exception.
   *
   * @covers ::summary
   */
  public function testSummaryThrowingException(): void {
    // Set the expected exception class. There is no message to check for.
    $this->expectException(InvalidPluginDefinitionException::class);

    $rules_action_base = new RulesActionBaseTestStub([], '', '');
    $rules_action_base->summary();
  }

  /**
   * Tests that the summary is being parsed from the label annotation.
   *
   * @covers ::summary
   */
  public function testSummaryParsingTheLabelAnnotation(): void {
    $rules_action_base = new RulesActionBaseTestStub([], '', ['label' => 'something']);
    $this->assertEquals('something', $rules_action_base->summary());
  }

  /**
   * Tests that a translation wrapper label is correctly parsed.
   *
   * @covers ::summary
   */
  public function testTranslatedLabel(): void {
    $translation_wrapper = $this->prophesize(TranslatableMarkup::class);
    $translation_wrapper->__toString()->willReturn('something');
    $rules_action_base = new RulesActionBaseTestStub([], '', ['label' => $translation_wrapper->reveal()]);
    $this->assertEquals('something', $rules_action_base->summary());
  }

}

/**
 * Class providing a concrete class extending RulesActionBase.
 */
class RulesActionBaseTestStub extends RulesActionBase {

}
