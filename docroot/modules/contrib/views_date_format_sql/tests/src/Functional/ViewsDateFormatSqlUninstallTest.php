<?php

namespace Drupal\Tests\views_date_format_sql\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;

/**
 * Tests views_date_format_sql uninstallation.
 *
 * @group views_date_format_sql
 */
class ViewsDateFormatSqlUninstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'file',
    'image',
    'media',
    'system',
    'text',
    'user',
    'views',
    'views_date_format_sql',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $privilegedUser = $this->createUser([
      'administer site configuration',
      'access media overview',
      'administer modules',
      'administer views',
      'access administration pages',
    ]);

    $this->drupalLogin($privilegedUser);

    // Re saves the view so that the plugin id and plugin id alone is updated.
    // This simulates having to use views_ui to click the field and then click
    // Save on the view.
    $view = Views::getView('media');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['fields']['changed']['plugin_id'] = 'views_date_format_sql_field';
    $view->save();
  }

  /**
   * Asserts that views_date_format_sql uninstalls non-dependent view.
   */
  public function testViewNotRemovedOnUninstall() {
    $this->drupalGet('/admin/modules/uninstall');
    $uninstall_edit = ['uninstall[views_date_format_sql]' => TRUE];

    $this->submitForm($uninstall_edit, 'Uninstall');
    $this->assertSession()->pageTextNotContains('Media');
    $this->submitForm([], 'Uninstall');
    $this->assertSession()->pageTextContains('The selected modules have been uninstalled.');

    // Checks if media page is accessible.
    $this->drupalGet('/admin/content/media');
    $this->assertSession()->pageTextContains('Updated');

    // Checks if view handler is working.
    // @see \Drupal\Tests\views_ui\Functional\HandlerTest::testBrokenHandlers().
    $this->drupalGet('/admin/structure/views/view/media/edit');
    $this->assertSession()->pageTextNotContains('Broken/missing handler');
  }

  /**
   * Asserts that views_date_format_sql uninstalls dependent view.
   */
  public function testViewRemovedOnUninstall() {
    // Sets format_date_sql to TRUE and saves the view.
    $view = Views::getView('media');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['fields']['changed']['format_date_sql'] = 1;
    $view->save();

    $this->drupalGet('/admin/modules/uninstall');
    $uninstall_edit = ['uninstall[views_date_format_sql]' => TRUE];

    $this->submitForm($uninstall_edit, 'Uninstall');
    $this->assertSession()->pageTextContains('Media');
    $this->submitForm([], 'Uninstall');
    $this->assertSession()->pageTextContains('The selected modules have been uninstalled.');
  }

}
