<?php

declare(strict_types=1);

namespace Drupal\Tests\block_visibility_groups\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\block\Entity\Block;
use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Class BlockVisibilityGroupsOrderTest.
 *
 * @package Drupal\Tests\block_visibility_groups\FunctionalJavascript
 * @group block_visibility_groups
 */
#[RunTestsInSeparateProcesses]
class BlockVisibilityGroupsOrderTest extends WebDriverTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'block_visibility_groups',
  ];

  /**
   * Groups used in test.
   *
   * @var array
   */
  protected array $groups;

  /**
   * Placed blocks.
   *
   * @var array
   */
  protected array $blocks;

  /**
   * An array of values for blocks.
   *
   * @var array
   */
  protected array $blockValues;

  /**
   * Initial block order after setUp.
   *
   * @var array
   */
  protected array $orderInitial;

  /**
   * Order all blocks after a1 and a2 swapped.
   *
   * @var array
   */
  protected array $orderSwapped;

  /**
   * Order of global and group blocks that should not change.
   *
   * @var array
   */
  protected array $orderStableBlocks;

  /**
   * The virtual user administrating the test.
   *
   * @var \Drupal\user\UserInterface|false
   */
  protected UserInterface|false $adminUser;

  /**
   * Page URL.
   *
   * @var string
   */
  protected string $path;

  protected $defaultTheme = 'stark';

  /**
   * Test setup.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
      'view the administration theme',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create some block_visibility_groups.
    $configs['request'] = [
      'id'     => 'request_path',
      'pages'  => '/node/*',
      'negate' => 0,
    ];
    $this->groups = [
      'group_a' => $this->createGroup($configs, 'group_a'),
      'group_b' => $this->createGroup($configs, 'group_b'),
    ];

    // Start with no placed blocks
    $this->blocks = [];

    // Enable some test blocks.
    // weights must be between -N/2 and +N/2, with N the number of blocks
    $this->blockValues = [
      'g1' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'g1', 'label' => 'G1'],
        'group'        => '',
        'setup_weight' => -3,
      ],
      'g2' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'g2', 'label' => 'G2'],
        'group'        => '',
        'setup_weight' => -2,
      ],
      'g3' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'g3', 'label' => 'G3'],
        'group'        => '',
        'setup_weight' => -1,
      ],
      'a1' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'a1', 'label' => 'A1'],
        'group'        => 'group_a',
        'setup_weight' => 1,
      ],
      'a2' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'a2', 'label' => 'A2'],
        'group'        => 'group_a',
        'setup_weight' => 3,
      ],
      'b1' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'b1', 'label' => 'B1'],
        'group'        => 'group_b',
        'setup_weight' => 0,
      ],
      'b2' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'b2', 'label' => 'B2'],
        'group'        => 'group_b',
        'setup_weight' => 2,
      ],
      'b3' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'b3', 'label' => 'B3'],
        'group'        => 'group_b',
        'setup_weight' => 4,
      ],

    ];

    // Block order
    $this->orderInitial = ['g1', 'g2', 'g3', 'b1', 'a1', 'b2', 'a2', 'b3'];
    $this->orderSwapped = ['g1', 'g2', 'g3', 'b1', 'a2', 'b2', 'a1', 'b3'];
    $this->orderStableBlocks = ['g1', 'g2', 'g3', 'b1', 'b2', 'b3'];

    // Get block admin page
    $this->path = $this->getBlockLayoutPage('ALL-GROUP');

    // Ensure weight selects are visible.
    $this->findWeightsToggle('Show row weights')->click();

    // Place blocks
    $this->placeGlobalBlocks();
    $this->placeGroupBlocks('group_a');
    $this->placeGroupBlocks('group_b');

    // Reset to All Groups and set weights
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->setWeights();

    $this->assertSession()
      ->pageTextNotContains('You have unsaved changes.');

    $this->assertBlockOrder($this->orderInitial);

  }

  /**
   * Check that blocks have full weight range available.
   */
  public function testWeightRange(): void {

    // Check placed
    $this->assertBlocksPlaced();

    // Test weight range for a global block
    $this->getBlockLayoutPage();
    $this->assertWeightRange('g1');

    // Test weight range for a group block
    $this->getBlockLayoutPage('group_a');
    $this->assertWeightRange('a1');
  }

  /**
   * Test reordering blocks by changing weights - showing global blocks.
   */
  public function testBlockReorderByWeightShowGlobal(): void {

    // Show Group A with Globals
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(TRUE);
    $this->assertBlocksHidden(['b1', 'b2', 'b3']);

    // Swap a1 and a2 weights and save
    $this->setWeights(['a1' => 3, 'a2' => 1]);

    // Retest block order
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->assertBlockOrder($this->orderSwapped,
      "Swapping a1, a2 weights with global blocks showing.");
  }

  /**
   * Test reordering blocks by changing weights - hiding global blocks.
   */
  public function testBlockReorderByWeightHideGlobal(): void {

    // Show Group A only and reset original weights
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(FALSE);
    $this->assertBlocksHidden(['g1', 'g2', 'g3', 'b1', 'b2', 'b3']);
    $this->setWeights(['a1' => 3, 'a2' => 1]);

    // Test block order
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->assertBlockOrder($this->orderSwapped,
      "Swapping a1, a2 weights with global blocks hidden.");
  }

  /**
   * Test block reordering by dragging rows - showing global blocks.
   *
   * Until dragTo() issue is resolved, must only drag higher value to lower
   *
   * @see https://www.drupal.org/node/2769825
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testBlockReorderByDraggingShowGlobal(): void {
    // Show Group A with Globals
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(TRUE);
    // $this->assertBlocksHidden(['b1', 'b2', 'b3']);

    // Swap a1 and a2 order and save
    $this->dragBlockToTarget('a2', 'a1');
    $this->submitForm([], t('Save blocks'), 'block-admin-display-form');

    // Test order
    $msg = "Dragging a1 to a2 with global blocks showing";
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->assertBlockOrder(['a2', 'a1'], $msg);
    $this->assertBlockOrder($this->orderStableBlocks, $msg);
  }

  /**
   * Test block reordering by dragging rows - hiding global blocks.
   *
   * Until dragTo() issue is resolved, must only drag higher value to lower
   *
   * @see https://www.drupal.org/node/2769825
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testBlockReorderByDraggingHideGlobal(): void {

    // Show Group A without Globals
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(FALSE);
    // $this->assertBlocksHidden(['g1', 'g2', 'g3', 'b1', 'b2', 'b3']);
    $page = $this->getSession()->getPage();
    $this->assertFalse($page->has('xpath',
      '//div[class="messages messages--error"]'),
      "Illegal choice detected with global blocks hidden.");

    // Swap a1 and a2
    $this->dragBlockToTarget('a2', 'a1');
    $this->submitForm([], t('Save blocks'), 'block-admin-display-form');
    $this->assertFalse($page->has('xpath',
      '//div[class="messages messages--error"]'),
      "Illegal choice detected with global blocks hidden.");

    // Test order
    $msg = "Dragging a1 to a2 with global blocks hidden";
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->assertBlockOrder(['a2', 'a1'], $msg);
    $this->assertBlockOrder($this->orderStableBlocks, $msg);
  }

  /**
   * Assert that all $this->blocks are indeed placed.
   */
  protected function assertBlocksPlaced(): void {
    // Load blocks layout page, selecting All Groups.
    $this->getBlockLayoutPage('ALL-GROUP');
    $page = $this->getSession()->getPage();

    $blockIds = array_keys($this->blocks);

    // Check that blocks are there.
    foreach ($blockIds as $id) {
      $weightSelect = $page->findField("blocks[$id][weight]");
      $this->assertNotNull($weightSelect, "Block $id on page.");
    }
  }

  /**
   * Assert that the number of weights for a block is
   * at least the total number of placed blocks.
   *
   * @see BlockVisibilityGroupListBuilder::parent::buildBlocksForm()
   *
   * @param $blockId
   */
  protected function assertWeightRange($blockId): void {

    // We should have a placed block
    $this->assertTrue(key_exists($blockId, $this->blocks),
      "Block $blockId is not placed.");

    // Count placed blocks
    $count = count($this->blocks);

    // Apply appropriate query when the block is in a group
    $group_id = $this->blockValues[$blockId]['group'];
    if ($group_id) {
      $this->getBlockLayoutPage($group_id);
      $this->setShowGlobal(FALSE);
    }
    else {
      $this->getBlockLayoutPage();
    }
    $page = $this->getSession()->getPage();

    // Find and verify select field
    $weightSelect = $page->findField("blocks[$blockId][weight]");
    $this->assertTrue((bool) $weightSelect,
      "No weight select field for $blockId.");

    // Assert number of weight select options
    $options = $weightSelect->findAll('xpath', './option');
    $values = array_map(function ($option) {
      return $option->getValue();
    }, $options);
    $this->assertGreaterThanOrEqual($count, count($values),
      "with $count placed blocks, $blockId is only allowed weights " .
      join(' ', $values));
  }

  /**
   * Assert that should-be-hidden blocks actually are.
   *
   * @param $hiddenIds
   */
  protected function assertBlocksHidden($hiddenIds): void {
    $page = $this->getSession()->getPage();
    foreach ($hiddenIds as $id) {
      $query = "//tr[@data-drupal-selector='edit-blocks-$id']";
      $row = $page->find('xpath', $query);
      if ($row) {
        $this->assertTrue($row->has('css', '.hidden'),
          "Block $id is visible -- but shouldn't be.");
      }
    }
  }

  /**
   * Are blocks ordered in Block layouts page as expected?
   *
   * @param $expectedIds
   * @param string $message
   */
  protected function assertBlockOrder($expectedIds, string $message = ''): void {
    $page = $this->getSession()->getPage();
    $rows = $page->findAll('xpath', '//tr[@data-drupal-selector]');
    $actualIds = [];
    foreach ($rows as $row) {
      $selector = $row->getAttribute('data-drupal-selector');
      foreach ($expectedIds as $id) {
        if ($selector == "edit-blocks-$id") {
          $actualIds[] = $id;
          break;
        }
      }
    }
    $this->assertSame($expectedIds, $actualIds,
      'Unexpected order ' . $message);
  }

  /**
   * Drag block to reorder.
   *
   * @param $draggedId
   * @param $targetId
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function dragBlockToTarget($draggedId, $targetId): void {
    $page = $this->getSession()->getPage();
    $this->findWeightsToggle('Hide row weights')->click();

    $handle = "a.tabledrag-handle";

    // Get draggedId's handle
    $query = "//tr[@data-drupal-selector='edit-blocks-$draggedId']";
    $row = $page->find('xpath', $query);
    $this->assertNotNull($row, "failed $query");
    $dragged = $row->find('css', $handle);
    $this->assertNotNull($dragged, "failed $query/$handle");

    // Get target's handle
    $query = "//tr[@data-drupal-selector='edit-blocks-$targetId']";
    $row = $page->find('xpath', $query);
    $this->assertNotNull($row, "failed $query");
    $target = $row->find('css', $handle);
    $this->assertNotNull($target, "failed $query/$handle");

    // Drag and give JavaScript some time to manipulate DOM
    $dragged->dragTo($target);
    $this->assertJsCondition('jQuery(".tabledrag-changed-warning").is(":visible")');
    $this->assertFalse($page->has('css', '.messages.messages--error'),
      "Illegal choice detected after dragging.");
    $this->assertSession()->pageTextContains('You have unsaved changes.');
  }

  /**
   * HTTP get and return path to admin block layout page with selected
   * block visibility group, specified by its id.
   *
   * @param string $groupId
   *
   * @return string (path URL)
   */
  protected function getBlockLayoutPage(string $groupId = ''): string {
    $default_theme = $this->config('system.theme')->get('default');
    $path = 'admin/structure/block/list/' . $default_theme;
    $validIds = array_keys($this->groups);
    $validIds[] = 'ALL-GROUP';
    if (!$groupId || !in_array($groupId, $validIds)) {
      $groupId = 'UNSET-GROUP';
    }
    $this->drupalGet($path, [
      'query' => [
        'block_visibility_group' => $groupId,
      ],
    ]);
    $this->waitForAjaxLinks();

    return $path;
  }

  /**
   * Check or uncheck the 'Show Global Blocks' button.
   *
   * @param bool $check
   *   Whether global blocks should be shown.
   */
  protected function setShowGlobal(bool $check): void {
    $checkbox = $this->getSession()
      ->getPage()
      ->findField('block_visibility_group_show_global');
    $this->assertNotNull($checkbox);

    if ($checkbox->isChecked() !== $check) {
      $this->getSession()->executeScript(
        'window.blockVisibilityGroupsBeforeReload = true;',
      );
      if ($check) {
        $checkbox->check();
      }
      else {
        $checkbox->uncheck();
      }
      $this->assertJsCondition(
        'typeof window.blockVisibilityGroupsBeforeReload === "undefined"',
        10000,
        'Page reloaded after changing the Show Global Blocks setting.',
      );
      $this->waitForAjaxLinks();
    }

    $checkbox = $this->assertSession()
      ->fieldExists('block_visibility_group_show_global');
    $this->assertSame($check, $checkbox->isChecked());
  }

  /**
   * Waits until Drupal has initialized all AJAX links on the current page.
   */
  protected function waitForAjaxLinks(): void {
    $condition = 'document.querySelectorAll(' .
      '".use-ajax:not([data-once~=\\"ajax\\"])"' .
      ').length === 0';
    $this->assertJsCondition(
      $condition,
      10000,
      'AJAX links initialized.',
    );
  }

  /**
   * Finds the show/hide weight toggle element.
   *
   * @param string $expected_text
   *   The expected text on the element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The toggle element.
   */
  protected function findWeightsToggle(string $expected_text): NodeElement {
    $toggle = $this->getSession()->getPage()->findButton($expected_text);
    $this->assertNotEmpty($toggle);
    return $toggle;
  }

  /**
   * Set block weights using form.
   *
   * @param array $block_weights
   */
  protected function setWeights(array $block_weights = []): void {
    $page = $this->getSession()->getPage();

    try {
      if (count($block_weights)) {
        foreach ($block_weights as $id => $weight) {
          $select = $page->findField("blocks[$id][weight]");
          $select->selectOption((string) $weight);

        }
      }
      else {
        foreach ($this->blockValues as $id => $values) {
          $select = $page->findField("blocks[$id][weight]");
          $select->selectOption((string) $values['setup_weight']);
        }
      }
      $this->submitForm([], t('Save blocks'), 'block-admin-display-form');
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage());
    }
  }

  /**
   * Place global blocks used in test.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function placeGlobalBlocks(): void {

    $globalBlockValues = array_filter($this->blockValues,
      function ($values) {
        return $values['group'] == '';
      });

    // Place the global blocks
    foreach ($globalBlockValues as $blockId => $values) {
      $this->blocks[$blockId] = $this->drupalPlaceBlock(
        $values['plugin_id'],
        $values['settings']
      );
    }
    $this->getSession()->getPage()->pressButton('Save blocks');
  }

  /**
   * @param $group_id
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function placeGroupBlocks($group_id): void {

    // Find blocks assigned to group in setUp
    $groupBlockValues = array_filter($this->blockValues,
      function ($values) use ($group_id) {
        return $values['group'] == $group_id;
      });

    // Place blocks
    if (count($groupBlockValues)) {
      $this->path = $this->getBlockLayoutPage($group_id);
      foreach ($groupBlockValues as $blockId => $values) {
        $group = $this->groups[$group_id];
        $this->blocks[$blockId] = $this->placeBlockInGroup(
          $values['plugin_id'],
          $group->id(),
          $values['settings']);
      }
    }
    $this->getSession()->getPage()->pressButton('Save blocks');
  }

  /**
   * Create a block visibility group.
   *
   * @param array $configs
   *   conditions configurations
   * @param string $id
   *   defaults to random
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createGroup(array $configs, string $id = ''): EntityInterface {
    $id = $id ?: $this->randomMachineName();
    $settings = [
      'id'    => $id,
      'label' => $this->randomString(),
    ];
    $group = BlockVisibilityGroup::create($settings);
    $group->save();
    foreach ($configs as $config) {
      $group->addCondition($config);
    }
    $group->save();

    return $group;
  }

  /**
   * Create and place a block in a block visibility group.
   *
   * @param $plugin_id
   * @param $group_id
   * @param array $settings
   *
   * @return \Drupal\block\Entity\Block
   */
  protected function placeBlockInGroup(
    $plugin_id,
    $group_id,
    array $settings = [],
  ): Block {
    $settings += [
      'label_display' => 'visible',
      'label'         => $this->randomMachineName(),
      'visibility'    => [
        'condition_group' => ['block_visibility_group' => $group_id],
      ],
    ];

    return $this->drupalPlaceBlock($plugin_id, $settings);
  }

}
