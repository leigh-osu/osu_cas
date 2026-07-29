<?php

namespace Drupal\Tests\responsive_preview\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the toolbar integration.
 *
 * @group responsive_preview
 */
class ResponsivePreviewContentFormTest extends ResponsivePreviewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'toolbar',
    'node',
  ];

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->testUser = $this->drupalCreateUser([
      'access content',
      'access responsive preview',
      'access toolbar',
      'create article content',
      'edit own article content',
    ]);
  }

  /**
   * Assure that the toolbar integration works on content forms.
   */
  public function testBundleSettings() {
    $tab_xpath = '//nav[@id="toolbar-bar"]//div[contains(@class, "toolbar-tab-responsive-preview")]';
    $this->drupalLogin($this->testUser);

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementExists('xpath', $tab_xpath);

    $node = Node::create([
      'type' => 'article',
      'uid' => $this->testUser->id(),
      'title' => $this->randomString(),
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->elementExists('xpath', $tab_xpath);

    $node_type = NodeType::load('article');
    $node_type->setPreviewMode(DRUPAL_DISABLED);
    $node_type->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementNotExists('xpath', $tab_xpath);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->elementNotExists('xpath', $tab_xpath);
  }

  /**
   * Tests presence of the responsive preview AJAX trigger.
   */
  public function testFormAlterAddsAjaxTriggerField() {
    $this->drupalLogin($this->testUser);

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementExists('css', 'input#ajax_responsive_preview');

    // Create a node and check the edit form also has the field.
    $node = Node::create([
      'type' => 'article',
      'uid' => $this->testUser->id(),
      'title' => $this->randomString(),
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->elementExists('css', 'input#ajax_responsive_preview');

    // Disable preview mode for the content type; the field should disappear.
    $node_type = NodeType::load('article');
    $node_type->setPreviewMode(DRUPAL_DISABLED);
    $node_type->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->elementNotExists('css', 'input#ajax_responsive_preview');

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->elementNotExists('css', 'input#ajax_responsive_preview');
  }

}
