<?php

namespace Drupal\osu_migrations_forages\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Combo Paragraph Bundle Process Plugin.
 *
 * This migration purely copies the data from image_album fields into fields
 * on each media item referenced in the image album.
 */
#[MigrateProcess(
  id: 'image_album_media',
  handle_multiples: TRUE
)]
class ImageAlbumMediaProcess extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $migrateDb;

  /**
   * The Drupal migrate lookup service.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  private MigrateLookupInterface $migrateLookup;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new instance of the class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\MigrateLookupInterface $migrateLookup
   *   The migrate lookup service used for fetching migration-related data.
   *
   * @return void
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrateLookupInterface $migrateLookup, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migrateDb = Database::getConnection('default', 'migrate');
    $this->migrateLookup = $migrateLookup;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Creates a new instance of the class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns a new instance of the class.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('migrate.lookup'), $container->get('entity_type.manager'));
  }

  /**
   * Transforms the input value during the migration process.
   *
   * @param mixed $value
   *   The value to be transformed.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migrate executable that processes the migration.
   * @param \Drupal\migrate\Row $row
   *   The row object containing the data being processed.
   * @param string $destination_property
   *   The destination property for which the value is being transformed.
   *
   * @return mixed
   *   The transformed value to be used in the destination.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    foreach ($value as $val) {
      $new_fid = $this->migrateLookup->lookup('upgrade_d7_files', [$val['fid']]);
      /** @var \Drupal\media\Entity\Media[] $media */
      $media = $this->entityTypeManager->getStorage('media')
        ->loadByProperties([
          'bundle' => 'image',
          'field_media_image' => $new_fid[0]['fid'],
        ]);
      if (count($media) === 1) {
        $media = reset($media);
        $media_image_type = $row->getSourceProperty('field_photo_subject');
        $media_image_type_target_ids = [];
        foreach ($media_image_type as $targets) {
          $media_image_type_targets = $targets['tid'];
          $new_media_image_type = $this->migrateLookup->lookup('upgrade_d7_taxonomy_terms', [$media_image_type_targets]);
          foreach ($new_media_image_type as $new_media_image_type_target) {
            $media_image_type_target_ids[] = ['target_id' => $new_media_image_type_target['tid']];
          }
        }
        $media->set('field_media_image_type', $media_image_type_target_ids);

        $media_affiliation = $row->getSourceProperty('field_species_affiliation');
        $media_affiliation__target_ids = [];
        foreach ($media_affiliation as $targets) {
          $media_image_affiliation_targets = $targets['target_id'];
          $new_media_affiliation_type = $this->migrateLookup->lookup('upgrade_d7_node', [$media_image_affiliation_targets]);
          foreach ($new_media_affiliation_type as $new_media_affiliation_type_id) {
            $media_affiliation__target_ids[] = ['target_id' => $new_media_affiliation_type_id['nid']];
          }
        }
        $media->set('field_media_affiliation', $media_affiliation__target_ids);
        $media->save();
        return $media->get('mid')->value;
      }
    }
  }

}
