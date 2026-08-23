<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_patterns\Kernel;

use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ComponentPluginManager service.
 *
 * @internal
 */
#[CoversClass(ComponentPluginManager::class)]
#[Group('ui_patterns')]
#[RunTestsInSeparateProcesses]
final class ComponentPluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ui_patterns', 'ui_patterns_test'];

  /**
   * Themes to install.
   *
   * @var string[]
   */
  protected static $themes = [];

  /**
   * The component plugin manager from ui_patterns.
   */
  protected ComponentPluginManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = \Drupal::service('plugin.manager.sdc');
  }

  /**
   * Test the method hook_component_info_alter().
   */
  public function testHookComponentInfoAlter(): void {
    $definition = $this->manager->getDefinition('ui_patterns_test:test-component');
    self::assertEquals('Hook altered', $definition['variants']['hook']['title']);
  }

  /**
   * Test the method ::getCategories().
   */
  public function testGetCategories(): void {
    /** @var \Drupal\ui_patterns\ComponentPluginManager $manager */
    $manager = $this->manager;
    $categories = $manager->getCategories();
    self::assertNotEmpty($categories);
  }

  /**
   * Test the method ::getSortedDefinitions().
   */
  public function testGetSortedDefinitions(): void {
    /** @var \Drupal\ui_patterns\ComponentPluginManager $manager */
    $manager = $this->manager;
    $sortedDefinitions = $manager->getSortedDefinitions();
    self::assertNotEmpty($sortedDefinitions);
  }

  /**
   * Test the method ::getGroupedDefinitions().
   */
  public function testGetGroupedDefinitions(): void {
    /** @var \Drupal\ui_patterns\ComponentPluginManager $manager */
    $manager = $this->manager;
    $groupedDefinitions = $manager->getGroupedDefinitions();
    self::assertNotEmpty($groupedDefinitions);
  }

  /**
   * Test the method ::getNegotiatedGroupedDefinitions().
   */
  public function testGetNegotiatedGroupedDefinitions(): void {
    /** @var \Drupal\ui_patterns\ComponentPluginManager $manager */
    $manager = $this->manager;
    $sortedDefinitions = $manager->getSortedDefinitions();
    $groupedDefinitions = $manager->getNegotiatedGroupedDefinitions();
    self::assertNotEmpty($groupedDefinitions);
    self::assertArrayNotHasKey('ui_patterns_test:test-form-component-replaced', $groupedDefinitions['Other']);
    self::assertArrayHasKey('ui_patterns_test:test-form-component', $groupedDefinitions['Other']);
    self::assertArrayHasKey('ui_patterns_test:no-ui-component', $sortedDefinitions);
    self::assertArrayNotHasKey('ui_patterns_test:no-ui-component', $groupedDefinitions['Other']);
  }

  /**
   * Test the method ::getNegotiatedGroupedDefinitions().
   */
  public function testGetNegotiatedGroupedDefinitionsIncludeReplaces(): void {
    /** @var \Drupal\ui_patterns\ComponentPluginManager $manager */
    $manager = $this->manager;
    $sortedDefinitions = $manager->getSortedDefinitions();
    $groupedDefinitions = $manager->getNegotiatedGroupedDefinitions(NULL, 'label', TRUE);
    self::assertNotEmpty($groupedDefinitions);
    self::assertArrayHasKey('ui_patterns_test:test-form-component-replaced', $groupedDefinitions['Other']);
    self::assertArrayHasKey('ui_patterns_test:test-form-component', $groupedDefinitions['Other']);
    self::assertArrayHasKey('ui_patterns_test:no-ui-component', $sortedDefinitions);
    self::assertArrayNotHasKey('ui_patterns_test:no-ui-component', $groupedDefinitions['Other']);
  }

  /**
   * Test the method ::getNegotiatedSortedDefinitions().
   */
  public function testGetNegotiatedSortedDefinitions(): void {
    /** @var \Drupal\ui_patterns\ComponentPluginManager $manager */
    $manager = $this->manager;
    $groupedDefinitions = $manager->getNegotiatedSortedDefinitions();
    self::assertNotEmpty($groupedDefinitions);
    self::assertArrayNotHasKey('ui_patterns_test:test-form-component-replaced', $groupedDefinitions);
    self::assertArrayNotHasKey('ui_patterns_test:no-ui-component', $groupedDefinitions);
    self::assertArrayNotHasKey('ui_patterns_test:ui-component', $groupedDefinitions);
    self::assertArrayNotHasKey('ui_patterns_test:ui-component-replaces-no-ui', $groupedDefinitions);
    self::assertArrayHasKey('ui_patterns_test:test-form-component', $groupedDefinitions);
  }

}
