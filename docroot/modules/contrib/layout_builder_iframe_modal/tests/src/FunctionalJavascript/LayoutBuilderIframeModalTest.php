<?php

declare(strict_types=1);

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests the Layout Builder Iframe Modal interface.
 *
 * @group layout_builder_iframe_modal
 */
class LayoutBuilderIframeModalTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   *
   * @var string
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/layout_builder_iframe_modal';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder_iframe_modal',
    'layout_builder',
    'block',
    'node',
    'block_content',
    'contextual',
    'field_ui',
    'views',
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

    $this->createContentType(['type' => 'layout_builder_iframe_modal']);
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'create and edit custom blocks',
      'administer node display',
      'administer node fields',
      'access contextual links',
    ]));

    // Enable layout builder.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
  }

  /**
   * Test adding a section.
   */
  public function testAddSection(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default/layout');
    $this->clickLink('Add section');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $this->clickLink('One column');

    $this->switchToIframe();
    $page->fillField('Administrative label', 'My section');
    $page->pressButton('Add section');

    $this->switchFromIframe();
    $assert_session->linkExists('Configure My section');
  }

  /**
   * Test adding a block.
   */
  public function testAddBlock(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default/layout');
    $this->clickLink('Add block');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $this->clickLink('Powered by Drupal');

    $this->switchToIframe();
    $page->fillField('Title', 'Powered by Layout Builder Iframe Modal');
    $page->checkField('Display title');
    $page->pressButton('Add block');

    $this->switchFromIframe();
    $assert_session->pageTextContains('Powered by Layout Builder Iframe Modal');
  }

  /**
   * Test configuring a block.
   */
  public function testConfigureBlock(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default/layout');
    $this->clickContextualLink('.js-layout-builder-block', 'Configure');

    $this->switchToIframe();
    $page->fillField('Title', 'Contextual Links title');
    $page->checkField('Display title');
    $page->pressButton('Update');

    $this->switchFromIframe();
    $assert_session->pageTextContains('Contextual Links title');
  }

  /**
   * Switches to the layout builder iframe.
   */
  protected function switchToIframe(): void {
    $this->assertSession()->waitForElementVisible('css', '#drupal-lbim-modal');
    $this->getSession()->switchToIFrame('lbim-dialog-iframe');
  }

  /**
   * Switches from the layout builder iframe.
   */
  protected function switchFromIframe(): void {
    $this->getSession()->switchToIFrame();
    $this->assertTrue($this->assertSession()->waitForElementRemoved('css', '#drupal-lbim-modal'));
  }

}
