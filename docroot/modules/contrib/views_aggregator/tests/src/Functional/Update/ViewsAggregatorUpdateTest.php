<?php

declare(strict_types=1);

namespace Drupal\Tests\views_aggregator\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests that Views using views_aggregator are properly updated by update hook.
 *
 * @group views_aggregator
 * @group legacy
 */
class ViewsAggregatorUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $d10_specific_dump = DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz';
    $d11_specific_dump = DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz';

    // Can't use the same dump in D10 and D11.
    if (file_exists($d10_specific_dump)) {
      $core_dump = $d10_specific_dump;
    }
    else {
      $core_dump = $d11_specific_dump;
    }

    // Use core fixture and Views-Aggregator-specific fixture.
    $this->databaseDumpFiles = [
      $core_dump,
      __DIR__ . '/../../../fixtures/update/drupal-10.3.0.views_aggregator-fix-options-schema-3480009.php',
    ];
  }

  /**
   * Tests views_aggregator_update_10200().
   *
   * @see views_aggregator_update_10200()
   */
  public function testHookUpdate10200(): void {
    $key = 'display.default.display_options.style.options';

    // Load the 'views.view.va_fixture_table' View and check that it holds the
    // expected pre-update values.
    $view = $this->config('views.view.va_fixture_table');

    $value = $view->get($key . '.group_aggregation.group_aggregation_results');
    $this->assertIsString($value);
    $this->assertSame('1', $value);

    $value = $view->get($key . '.column_aggregation.precision');
    $this->assertIsString($value);
    $this->assertSame('2', $value);

    $value = $view->get($key . '.column_aggregation.totals_per_page');
    $this->assertIsString($value);
    $this->assertSame('1', $value);

    // Run updates.
    $this->runUpdates();

    // Load the 'views.view.va_fixture_table' View again. It should now hold the
    // expected post-update values.
    $view = $this->config('views.view.va_fixture_table');

    // Option group_aggregation_results was changed from string to integer.
    $value = $view->get($key . '.group_aggregation.group_aggregation_results');
    $this->assertIsInt($value);
    $this->assertSame(1, $value);

    // Option precision was changed from string to integer.
    $value = $view->get($key . '.column_aggregation.precision');
    $this->assertIsInt($value);
    $this->assertSame(2, $value);

    // Option totals_per_page was changed from string/boolean to integer.
    $value = $view->get($key . '.column_aggregation.totals_per_page');
    $this->assertIsInt($value);
    $this->assertSame(1, $value);
  }

}
