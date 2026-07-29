<?php

namespace Drupal\osu_migrations\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\osu_migrations\OsuMigrateMissingFiles;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process Plugin to transform Drupal 7 embed to Drupal 9.
 */
#[MigrateProcess(
  id: 'osu_wysiwyg_filter_missing_files'
)]
class OsuWysiwygFilterMissingFiles extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\osu_migrations\OsuMigrateMissingFiles
   */
  private OsuMigrateMissingFiles $osuMigrateMissingFiles;

  /**
   * Constructs a new instance of the class.
   *
   * @param array $configuration
   *   An array of configuration settings.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\osu_migrations\OsuMigrateMissingFiles $osuMigrateMissingFiles
   *   An instance of the OsuMigrateMissingFiles service.
   *
   * @return void
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OsuMigrateMissingFiles $osuMigrateMissingFiles) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->osuMigrateMissingFiles = $osuMigrateMissingFiles;
  }

  /**
   * Creates a new instance of the class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface for dependency injection.
   * @param array $configuration
   *   An array of configuration settings.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   A new instance of the class.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('osu_migrations.osu_migrate_missing_files'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value_is_array = is_array($value);
    $text = (string) ($value_is_array ? $value['value'] : $value);
    try {
      $this->osuMigrateMissingFiles->copyMissingFiles($text);
    }
    catch (\Exception $e) {
      $migrate_executable->saveMessage($e->getMessage());
    }

    return $value;
  }

}
