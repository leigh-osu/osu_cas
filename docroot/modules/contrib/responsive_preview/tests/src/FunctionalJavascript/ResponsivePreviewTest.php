<?php

namespace Drupal\Tests\responsive_preview\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the toolbar integration.
 *
 * @group responsive_preview
 */
class ResponsivePreviewTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'node',
    'responsive_preview',
    'toolbar',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $previewUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->previewUser = $this->drupalCreateUser([
      'access responsive preview',
      'access toolbar',
      'view test entity',
      'administer entity_test content',
      'create article content',
      'edit own article content',
    ]);
  }

  /**
   * Tests that the toolbar integration works properly.
   */
  public function testToolbarIntegration() {
    $entity = EntityTest::create();
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $this->drupalLogin($this->previewUser);

    $this->drupalGet($entity->toUrl());
    $this->selectDevice('(//*[@id="responsive-preview-toolbar-tab"]//button[@data-responsive-preview-name])[1]');
    $element = $this->assertSession()->waitForElementVisible('css', '#responsive-preview-orientation');
    $this->assertStringNotContainsString('rotated', $element->getAttribute('class'));

    $this->assertTrue($this->getSession()->getDriver()->evaluateScript(
      "(document.getElementById('responsive-preview-frame').contentWindow.location.href.indexOf('/entity_test/1') !== -1)"
    ), 'The responsive preview iframe is not displaying the expected URL.');
  }

  /**
   * Tests that preview works on node edit.
   */
  public function testContentEdit() {
    $this->drupalLogin($this->previewUser);

    $node = Node::create([
      'type' => 'article',
      'uid' => $this->previewUser->id(),
      'title' => $this->randomString(),
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');

    $this->selectDevice('(//*[@id="responsive-preview-toolbar-tab"]//button[@data-responsive-preview-name])[1]');
    $element = $this->assertSession()->waitForElementVisible('css', '#responsive-preview-orientation');
    $this->assertStringNotContainsString('rotated', $element->getAttribute('class'));

    $this->getSession()->wait(5000,
      "!!(document.getElementById('responsive-preview-frame')
&& document.getElementById('responsive-preview-frame').contentWindow
&& document.getElementById('responsive-preview-frame').contentWindow.location.href.indexOf('/node/preview/" . $node->uuid() . "/full') !== -1)"
    );
    $this->assertTrue($this->getSession()->getDriver()->evaluateScript(
      "(document.getElementById('responsive-preview-frame').contentWindow.location.href.indexOf('/node/preview/" . $node->uuid() . "/full') !== -1)"
    ), 'The responsive preview iframe is not displaying the expected URL.');
  }

  /**
   * Select device for device preview.
   *
   * NOTE: Index starts from 1.
   *
   * @param int $xpath_device_button
   *   The index number of device in drop-down list.
   */
  protected function selectDevice($xpath_device_button) {
    $page = $this->getSession()->getPage();

    $page->find('css', '#responsive-preview-toolbar-tab button')->click();
    // Wait for the dropdown.
    $this->assertSession()->waitForElementVisible('css', '.responsive-preview-options');

    $page->find('xpath', $xpath_device_button)->click();
    // Wait for the responsive preview iframe.
    $this->assertSession()->waitForElementVisible('css', '#responsive-preview-frame');
  }

}
