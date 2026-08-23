<?php

namespace Drupal\Tests\eva\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;

/**
 * Test eva view.
 *
 * @group eva
 */
class EvaViewTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'eva',
    'views',
    'views_ui',
    'eva_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $adminUser = $this->drupalCreateUser();
    $adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $adminUser->save();
    $this->drupalLogin($adminUser);

    $this->createContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Tests the EVA view title display and toggle functionality.
   */
  public function testEvaViewTitle() {
    $view_title = 'foo & bar';

    $view = View::create([
      'id' => 'test',
      'label' => 'Test',
      'base_table' => 'users_field_data',
      'base_field' => 'uid',
      'display' => [
        'default' => [
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [],
        ],
        'entity_view_1' => [
          'display_title' => 'Eva',
          'display_plugin' => 'entity_view',
          'id' => 'entity_view_1',
          'display_options' => [
            'title' => $view_title,
            'entity_type' => 'node',
            'show_title' => TRUE,
          ],
        ],
      ],
    ]);
    $view->save();

    $node = $this->drupalCreateNode(['type' => 'article']);
    $nid = $node->id();

    $this->drupalGet("node/$nid");

    $this->assertSession()->pageTextContains($view_title, 'EVA view title should appear on the node page.');

    // Disable eva title.
    $view = Views::getView('test');
    $view->setDisplay('entity_view_1');
    $view->displayHandlers->get('entity_view_1')->setOption('show_title', FALSE);
    $view->save();

    $this->drupalGet("node/$nid");

    $this->assertSession()->pageTextNotContains($view_title, 'EVA view title should not appear after disabling it.');
  }

  /**
   * Tests the empty text behavior of an EVA view attached to a table display.
   */
  public function testEmptyTextBehaviorWithTable() {
    $view = View::load('3111965_eva');
    $view->setStatus(TRUE);
    $view->save();

    $this->drupalGet('user/1');

    $page_text = $this->getSession()->getPage()->getText();
    $occurrences = substr_count($page_text, 'No content available.');

    $this->assertSame(1, $occurrences, 'Empty view result behavior should only once per page.');
  }

}
