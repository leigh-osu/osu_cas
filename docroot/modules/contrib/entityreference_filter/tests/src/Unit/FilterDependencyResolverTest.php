<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\entityreference_filter\Service\FilterArgumentsResolver;
use Drupal\entityreference_filter\Service\FilterDependencyResolver;

/**
 * Tests the FilterDependencyResolver service.
 *
 * @group entityreference_filter
 *
 * @coversDefaultClass \Drupal\entityreference_filter\Service\FilterDependencyResolver
 */
class FilterDependencyResolverTest extends UnitTestCase {

  /**
   * The resolver under test.
   */
  protected FilterDependencyResolver $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resolver = new FilterDependencyResolver(new FilterArgumentsResolver());
  }

  /**
   * Builds a filter definition for the graph.
   */
  protected function filter(string $identifier, string $referenceArguments = ''): array {
    return [
      'identifier' => $identifier,
      'reference_arguments' => $referenceArguments,
    ];
  }

  /**
   * @covers ::buildGraph
   */
  public function testBuildGraphLinearChain(): void {
    $graph = $this->resolver->buildGraph([
      'a' => $this->filter('a'),
      'b' => $this->filter('b', '[a]'),
      'c' => $this->filter('c', '[b]'),
    ]);

    $this->assertSame(['b'], $graph['a']);
    $this->assertSame(['c'], $graph['b']);
    $this->assertSame([], $graph['c']);
  }

  /**
   * @covers ::detectCycle
   */
  public function testNoCycleForAcyclicGraph(): void {
    $graph = $this->resolver->buildGraph([
      'a' => $this->filter('a'),
      'b' => $this->filter('b', '[a]'),
      'c' => $this->filter('c', '[b]'),
    ]);
    $this->assertSame([], $this->resolver->detectCycle($graph));
  }

  /**
   * @covers ::detectCycle
   */
  public function testDetectsTwoNodeCycle(): void {
    $graph = $this->resolver->buildGraph([
      'a' => $this->filter('a', '[b]'),
      'b' => $this->filter('b', '[a]'),
    ]);
    $this->assertNotEmpty($this->resolver->detectCycle($graph));
  }

  /**
   * @covers ::detectCycle
   */
  public function testDetectsSelfReference(): void {
    $graph = $this->resolver->buildGraph([
      'a' => $this->filter('a', '[a]'),
    ]);
    $this->assertSame(['a', 'a'], $this->resolver->detectCycle($graph));
  }

  /**
   * @covers ::descendantsTopological
   */
  public function testDescendantsLinearChain(): void {
    $graph = $this->resolver->buildGraph([
      'a' => $this->filter('a'),
      'b' => $this->filter('b', '[a]'),
      'c' => $this->filter('c', '[b]'),
    ]);
    $this->assertSame(['b', 'c'], $this->resolver->descendantsTopological($graph, 'a'));
  }

  /**
   * @covers ::descendantsTopological
   */
  public function testDescendantsDiamondOrdersDependentLast(): void {
    // Edges a->b, a->c, b->d, c->d: d must come after both b and c.
    $graph = $this->resolver->buildGraph([
      'a' => $this->filter('a'),
      'b' => $this->filter('b', '[a]'),
      'c' => $this->filter('c', '[a]'),
      'd' => $this->filter('d', '[b]/[c]'),
    ]);
    $order = $this->resolver->descendantsTopological($graph, 'a');

    $this->assertContains('d', $order);
    $this->assertGreaterThan(array_search('b', $order, TRUE), array_search('d', $order, TRUE));
    $this->assertGreaterThan(array_search('c', $order, TRUE), array_search('d', $order, TRUE));
  }

  /**
   * @covers ::computeEffectiveValue
   * @dataProvider effectiveValueProvider
   */
  public function testComputeEffectiveValue(array $current, array $options, bool $required, bool $multiple, array|string $expected): void {
    $this->assertSame($expected, $this->resolver->computeEffectiveValue($current, $options, $required, $multiple));
  }

  /**
   * Data provider for testComputeEffectiveValue().
   */
  public static function effectiveValueProvider(): array {
    return [
      'single keeps valid value' => [['2'], ['1', '2', '3'], FALSE, FALSE, '2'],
      'single optional empty -> All' => [[], ['1', '2', '3'], FALSE, FALSE, 'All'],
      'single required empty -> first' => [[], ['1', '2', '3'], TRUE, FALSE, '1'],
      'single optional invalid -> All' => [['9'], ['1', '2', '3'], FALSE, FALSE, 'All'],
      'single required invalid -> first' => [['9'], ['1', '2', '3'], TRUE, FALSE, '1'],
      'multiple keeps valid subset' => [['2', '3'], ['1', '2', '3'], FALSE, TRUE, ['2', '3']],
      'multiple drops invalid' => [['2', '9'], ['1', '2', '3'], FALSE, TRUE, ['2']],
      'required but no options -> All' => [[], [], TRUE, FALSE, 'All'],
    ];
  }

}
