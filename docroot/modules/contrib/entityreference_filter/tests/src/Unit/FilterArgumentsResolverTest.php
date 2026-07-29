<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Unit;

use Drupal\entityreference_filter\Service\FilterArgumentsResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the filter arguments resolver.
 *
 * @coversDefaultClass \Drupal\entityreference_filter\Service\FilterArgumentsResolver
 *
 * @group entityreference_filter
 */
class FilterArgumentsResolverTest extends UnitTestCase {

  /**
   * The resolver under test.
   */
  protected FilterArgumentsResolver $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resolver = new FilterArgumentsResolver();
  }

  /**
   * Returns an exposed filter value callback backed by an array.
   *
   * @param array $values
   *   Exposed filter values keyed by filter identifier.
   *
   * @return callable
   *   The value callback.
   */
  protected function exposedValues(array $values = []): callable {
    return static fn (string $name) => $values[$name] ?? NULL;
  }

  /**
   * @covers ::resolve
   */
  public function testEmptyAndWhitespaceOnlyStringResolvesToNoArgs(): void {
    $this->assertSame([], $this->resolver->resolve('', [], [], $this->exposedValues()));
    $this->assertSame([], $this->resolver->resolve('  ', [], [], $this->exposedValues()));
  }

  /**
   * @covers ::resolve
   */
  public function testLiteralArgumentsArePassedThrough(): void {
    $this->assertSame(['foo', 'bar'], $this->resolver->resolve('foo/bar', [], [], $this->exposedValues()));
  }

  /**
   * @covers ::resolve
   */
  public function testUrlArguments(): void {
    $view_args = ['10', '20'];
    $this->assertSame(['10'], $this->resolver->resolve('!1', $view_args, [], $this->exposedValues()));
    $this->assertSame(['20', '10'], $this->resolver->resolve('!2/!1', $view_args, [], $this->exposedValues()));
    // Out of range resolves to NULL.
    $this->assertSame([NULL], $this->resolver->resolve('!3', $view_args, [], $this->exposedValues()));
    // `!0` and `!` have no valid position and are left as literals.
    $this->assertSame(['!0'], $this->resolver->resolve('!0', $view_args, [], $this->exposedValues()));
    $this->assertSame(['!'], $this->resolver->resolve('!', $view_args, [], $this->exposedValues()));
  }

  /**
   * @covers ::resolve
   */
  public function testContextualArguments(): void {
    $context_args = ['5', '7'];
    $this->assertSame(['7', '5'], $this->resolver->resolve('#2/#1', [], $context_args, $this->exposedValues()));
    $this->assertSame([NULL], $this->resolver->resolve('#3', [], $context_args, $this->exposedValues()));
    // Without contextual values the token is left as a literal.
    $this->assertSame(['#1'], $this->resolver->resolve('#1', [], [], $this->exposedValues()));
  }

  /**
   * @covers ::resolve
   */
  public function testExposedFilterArguments(): void {
    $values = $this->exposedValues(['color' => '3', 'tags' => ['1', '2']]);
    $this->assertSame(['3'], $this->resolver->resolve('[color]', [], [], $values));
    // Multiple values are glued with '+'.
    $this->assertSame(['1+2'], $this->resolver->resolve('[tags]', [], [], $values));
    // Unknown filters resolve to NULL.
    $this->assertSame([NULL], $this->resolver->resolve('[missing]', [], [], $values));
  }

  /**
   * @covers ::resolve
   */
  public function testAllAndEmptyArrayNormalizeToNull(): void {
    $values = $this->exposedValues(['color' => 'All', 'tags' => []]);
    $this->assertSame([NULL], $this->resolver->resolve('[color]', [], [], $values));
    $this->assertSame([NULL], $this->resolver->resolve('[tags]', [], [], $values));
    $this->assertSame([NULL], $this->resolver->resolve('All', [], [], $values));
  }

  /**
   * @covers ::resolve
   */
  public function testJoinedExposedFiltersInOneArgument(): void {
    $values = $this->exposedValues(['b' => '2', 'c' => '3']);
    // Two tokens in one item are glued with '+' into a single argument.
    $this->assertSame(['2+3'], $this->resolver->resolve('[b]+[c]', [], [], $values));

    // A token with no selection ('All') is dropped from the join.
    $values = $this->exposedValues(['b' => 'All', 'c' => '3']);
    $this->assertSame(['3'], $this->resolver->resolve('[b]+[c]', [], [], $values));

    // All tokens empty -> NULL.
    $values = $this->exposedValues(['b' => 'All', 'c' => []]);
    $this->assertSame([NULL], $this->resolver->resolve('[b]+[c]', [], [], $values));

    // A joined item keeps its position next to other items.
    $values = $this->exposedValues(['b' => '2', 'c' => '3']);
    $this->assertSame(['x', '2+3'], $this->resolver->resolve('x/[b]+[c]', [], [], $values));
  }

  /**
   * @covers ::resolve
   */
  public function testMixedArgumentsKeepPositions(): void {
    $resolved = $this->resolver->resolve(
      'static/!1/[color]/#1',
      ['url-arg'],
      ['ctx-arg'],
      $this->exposedValues(['color' => '9'])
    );
    $this->assertSame(['static', 'url-arg', '9', 'ctx-arg'], $resolved);
  }

  /**
   * @covers ::getControllingFilters
   */
  public function testGetControllingFilters(): void {
    $this->assertSame([], $this->resolver->getControllingFilters(''));
    $this->assertSame([], $this->resolver->getControllingFilters('!1/#2/static'));
    $this->assertSame(['color', 'tags'], $this->resolver->getControllingFilters('!1/[color]/static/[tags]'));
    // Several tokens sharing one item are all returned, de-duplicated.
    $this->assertSame(['b', 'c'], $this->resolver->getControllingFilters('[b]+[c]'));
    $this->assertSame(['a'], $this->resolver->getControllingFilters('[a]+[a]'));
  }

}
