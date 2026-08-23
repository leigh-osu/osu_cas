<?php

declare(strict_types=1);

namespace Drupal\Tests\date_ap_style\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;

/**
 * Tests configuration schema validation for Date AP Style module.
 */
class ConfigValidationTest extends KernelTestBase {

  use SchemaCheckTrait;
  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'user',
    'node',
    'date_ap_style',
  ];

  /**
   * The config schema checker.
   *
   * @var \Drupal\Core\Config\Development\ConfigSchemaChecker
   */
  protected $configSchemaChecker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas needed for the tests (user first, then node)
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Install only our module config.
    $this->installConfig(['date_ap_style']);

    // Install the recipe from our test fixtures in setUp so all tests have
    // recipe configs. The recipe will create the date_test content type.
    $recipe_path = dirname(__DIR__, 2) . '/fixtures/recipe';
    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);

    $this->configSchemaChecker = $this->container->get('config.typed');
  }

  /**
   * Tests configuration schema validation for all AP Style configurations.
   *
   * Validates that all module configurations pass schema constraint validation.
   */
  public function testConfigurationSchemaValidation(): void {
    // Get all configuration and validate each one.
    $config_factory = $this->container->get('config.factory');
    $typed_config_manager = $this->container->get('config.typed');
    $all_config = $config_factory->listAll();

    // Test the specific configs we know contain AP Style formatter settings.
    $configs_to_test = [
      'date_ap_style.settings',
      'core.entity_view_display.node.date_test.default',
      'core.entity_view_display.node.date_test.ap_style_test',
    ];

    $our_configs = array_intersect($all_config, $configs_to_test);

    $errors = [];
    foreach ($our_configs as $config_name) {
      if (!$typed_config_manager->hasConfigSchema($config_name)) {
        continue;
      }

      try {
        $typed_config = $typed_config_manager->get($config_name);
        $violations = $typed_config->validate();

        if ($violations->count() > 0) {
          foreach ($violations as $violation) {
            $errors[] = sprintf(
              'Config: %s, Property: %s, Error: %s',
              $config_name,
              $violation->getPropertyPath(),
              $violation->getMessage()
            );
          }
        }
      }
      catch (\Exception $e) {
        $errors[] = sprintf('Config: %s, Exception: %s', $config_name, $e->getMessage());
      }
    }

    // Assert no validation errors for our configurations.
    $this->assertCount(0, $errors, 'AP Style configuration validation errors: ' . implode('; ', $errors));
  }

}
