<?php

declare(strict_types=1);

namespace Drupal\Tests\rules\Unit;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\rules\Context\ExecutionStateInterface;
use Drupal\rules\Engine\ConditionExpressionContainer;
use Drupal\rules\Engine\ConditionExpressionContainerInterface;
use Drupal\rules\Engine\ExpressionManagerInterface;

/**
 * @coversDefaultClass \Drupal\rules\Engine\ConditionExpressionContainer
 * @group Rules
 */
class RulesConditionContainerTest extends RulesUnitTestBase {

  /**
   * Creates a condition expression container stub.
   *
   * @return \Drupal\rules\Engine\ConditionExpressionContainerInterface
   *   A concrete class implementing a condition expression container.
   */
  protected function getConditionContainerStub(): ConditionExpressionContainerInterface {
    return new RulesConditionContainerTestStub(
      [],
      'test_id',
      [],
      $this->prophesize(ExpressionManagerInterface::class)->reveal(),
      $this->prophesize(LoggerChannelInterface::class)->reveal(),
    );
  }

  /**
   * Tests adding conditions to the condition container.
   *
   * @covers ::addExpressionObject
   */
  public function testAddExpressionObject(): void {
    $container = $this->getConditionContainerStub();
    $container->addExpressionObject($this->trueConditionExpression->reveal());

    $property = new \ReflectionProperty($container, 'conditions');
    $property->setAccessible(TRUE);

    $this->assertEquals([$this->trueConditionExpression->reveal()], array_values($property->getValue($container)));
  }

  /**
   * Tests negating the result of the condition container.
   *
   * @covers ::negate
   * @covers ::isNegated
   */
  public function testNegate(): void {
    $container = $this->getConditionContainerStub();

    $this->assertFalse($container->isNegated());
    $this->assertTrue($container->execute());

    $container->negate(TRUE);
    $this->assertTrue($container->isNegated());
    $this->assertFalse($container->execute());
  }

  /**
   * Tests executing the condition container.
   *
   * @covers ::execute
   */
  public function testExecute(): void {
    $container = $this->getConditionContainerStub();
    $this->assertTrue($container->execute());
  }

  /**
   * Tests that an expression can be retrieved by UUID.
   */
  public function testLookupExpression(): void {
    $container = $this->getConditionContainerStub();
    $container->addExpressionObject($this->trueConditionExpression->reveal());
    $uuid = $this->trueConditionExpression->reveal()->getUuid();
    $this->assertSame($this->trueConditionExpression->reveal(), $container->getExpression($uuid));
    $this->assertFalse($container->getExpression('invalid UUID'));
  }

  /**
   * Tests that a nested expression can be retrieved by UUID.
   */
  public function testLookupNestedExpression(): void {
    $container = $this->getConditionContainerStub();
    $container->addExpressionObject($this->trueConditionExpression->reveal());

    $nested_container = $this->getConditionContainerStub();
    $nested_container->addExpressionObject($this->falseConditionExpression->reveal());

    $container->addExpressionObject($nested_container);

    $uuid = $this->falseConditionExpression->reveal()->getUuid();
    $this->assertSame($this->falseConditionExpression->reveal(), $container->getExpression($uuid));
  }

  /**
   * Tests deleting a condition from the container.
   */
  public function testDeletingCondition(): void {
    $container = $this->getConditionContainerStub();
    $container->addExpressionObject($this->trueConditionExpression->reveal());
    $container->addExpressionObject($this->falseConditionExpression->reveal());

    // Delete the first condition.
    $uuid = $this->trueConditionExpression->reveal()->getUuid();
    $this->assertTrue($container->deleteExpression($uuid));
    foreach ($container as $condition) {
      $this->assertSame($this->falseConditionExpression->reveal(), $condition);
    }

    $this->assertFalse($container->deleteExpression('invalid UUID'));
  }

  /**
   * Tests deleting a nested condition from the container.
   */
  public function testDeletingNestedCondition(): void {
    $container = $this->getConditionContainerStub();
    $container->addExpressionObject($this->trueConditionExpression->reveal());

    $nested_container = $this->getConditionContainerStub();
    $nested_container->addExpressionObject($this->falseConditionExpression->reveal());

    $container->addExpressionObject($nested_container);

    $uuid = $this->falseConditionExpression->reveal()->getUuid();
    $this->assertTrue($container->deleteExpression($uuid));
    $this->assertCount(0, $nested_container->getIterator());
  }

}

/**
 * Class used for overriding evaluate() as this does not work with PHPunit.
 */
class RulesConditionContainerTestStub extends ConditionExpressionContainer {

  /**
   * Implements one abstract method on ConditionExpressionContainer.
   */
  public function evaluate(ExecutionStateInterface $state): bool {
    return TRUE;
  }

  /**
   * Implements one abstract method on ConditionExpressionContainer.
   */
  protected function allowsMetadataAssertions() {
    return TRUE;
  }

}
