<?php

/**
 * @file
 * Batch processing API for populate Media field of the entity with image items.
 */

use Drupal\Core\Entity\EntityInterface;

/**
 * Populate Media field of the entity with image items.
 *
 * Create Media entities based on existing image fields.
 * Populate the Media field, that was created, with the Media entities.
 *
 * @param string $entity_type_id
 *   The entity type id.
 * @param array $bundles
 *   The bundles of the entity type that has the image field.
 * @param string $image_field_name
 *   The name of the Image field.
 * @param string $media_field_name
 *   The name of the created Media field.
 * @param array $context
 *   Batch context.
 */
function image_field_to_media_populate_media_field(string $entity_type_id, array $bundles, string $image_field_name, string $media_field_name, array &$context): void {
  if (empty($context['sandbox'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] = image_field_to_media_get_total_entities($entity_type_id, $bundles, $image_field_name);
  }

  $type_key = match ($entity_type_id) {
    'taxonomy_term' => 'vid',
    default => 'type',
  };

  $counter = $context['sandbox']['progress'];

  $query = \Drupal::entityTypeManager()->getStorage($entity_type_id)
    ->getQuery()
    ->accessCheck(TRUE)
    ->exists($image_field_name)
    ->range($counter, 1);

  // If we clone Image fields to Media in update hooks, we can skip specifying
  // bundles.
  // See https://www.drupal.org/project/image_field_to_media/issues/3438068
  if ($bundles !== []) {
    $query->condition($type_key, $bundles, 'IN');
  }

  $entity_id = $query->execute();

  // Check if all entities have been processed and if so, then set
  // $context['finished'] = 1 to finish the operation.
  if (!$entity_id) {
    $context['finished'] = 1;
    // We need this to enable cloning of Image fields to Media in update hooks.
    // See https://www.drupal.org/project/image_field_to_media/issues/3438068
    $context['#finished'] = 1;
    return;
  }

  $entity_id = reset($entity_id);
  $entity = \Drupal::entityTypeManager()
    ->getStorage($entity_type_id)
    ->load($entity_id);

  // In case of adding images to the existing Media field it's possible that
  // the media field wasn't added to all bundles which have image fields.
  // Therefore, we should check that the entity has the media field.
  if ($entity->hasField($media_field_name)) {
    $images = $entity->get($image_field_name)->getValue();

    foreach ($images as $image) {
      $fid = $image['target_id'];
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($fid);

      // There have been cases where data about image files was lost and this
      // caused error and stops the conversion process. So let's check for this.
      if (!$file) {
        $message = 'Missing file with ID: %fid was on %entity_type_id %bundle %entity_id.';
        $message = 'Media cannot be created. The %entity_type_id with ID: %entity_id of bundle: %bundle refers to the image file with ID: %fid. But there is no information about the file with this ID in the database.';
        \Drupal::logger('image_field_to_media')->error($message, [
          '%fid' => $fid,
          '%entity_type_id' => $entity_type_id,
          '%bundle' => $entity->bundle(),
          '%entity_id' => $entity_id,
        ]);
      }
      else {
        $file_uri = $file->getFileUri();
        $media = image_field_to_media_get_media_entity($file_uri, $image);
        $entity->get($media_field_name)->appendItem($media);
      }
    }

    $entity->save();
  }

  $context['sandbox']['progress']++;
  $context['results']['processed'] = $context['sandbox']['progress'];
  $context['message'] = t('Processed @current out of @total entities.', [
    '@current' => $context['sandbox']['progress'],
    '@total' => $context['sandbox']['max'],
  ]);

  $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  // We need this to enable cloning of Image fields to Media in update hooks.
  // See https://www.drupal.org/project/image_field_to_media/issues/3438068
  $context['#finished'] = $context['finished'];
}

/**
 * Get the number of entities that have the image field.
 *
 * @param string $entity_type_id
 *   The entity type id.
 * @param array $bundles
 *   The bundles of the entity type that has the image field.
 * @param string $image_field_name
 *   The name of the Image field.
 *
 * @return int
 *   The number of entities that have the image field.
 */
function image_field_to_media_get_total_entities(string $entity_type_id, array $bundles, string $image_field_name): int {
  $type_key = match ($entity_type_id) {
    'taxonomy_term' => 'vid',
    default => 'type',
  };

  $query = \Drupal::entityTypeManager()->getStorage($entity_type_id)
    ->getQuery()
    ->accessCheck(TRUE)
    ->exists($image_field_name)
    ->count();

  // If we clone Image fields to Media in update hooks, we can skip specifying
  // bundles.
  // See https://www.drupal.org/project/image_field_to_media/issues/3438068
  if ($bundles !== []) {
    $query->condition($type_key, $bundles, 'IN');
  }

  $total_entities = $query->execute();
  return $total_entities;
}

/**
 * Return or new or existed Media entity.
 *
 * If a Media entity with such image already exists, then return this entity.
 * To implement this we store sha1 hashes of image files after creation of
 * new Media entities.
 *
 * @param string $file_uri
 *   URI of the image file.
 * @param array $image
 *   The array that represents one item of the Image field.
 *
 * @return \Drupal\core\entity\EntityInterface
 *   The media Entity with the image.
 */
function image_field_to_media_get_media_entity(string $file_uri, array $image): EntityInterface {
  // Get stored hashes of image files.
  // The structure of the array: ['media id' => 'hash of image file'].
  // 'media id' is id of the Media entity that contains the image file that was
  // hashed.
  $hashes = \Drupal::state()->get('image_field_to_media.hashes_of_image_files', []);

  // Get hash of the current image file.
  $hash = sha1_file($file_uri);

  // If some image source files do not exist or if you run the process
  // in a test environment where not all files are synchronized, then
  // sha1_file($file_uri) returns FALSE. In this case we should create a new
  // Media object with the current image, and not store the hash in the state.
  if ($hash) {
    // Check if the Media entity with such image already exists.
    // Compare hash of the image file with hashes that was stored previously.
    // The structure of the $hashes array: ['media id' => 'hash of image file']
    // 'media id' is id of the Media entity that contains the image file that
    // was hashed.
    $mid = array_search($hash, $hashes);
    if ($mid) {
      // Load the Media entity that has the current image and return it.
      return \Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($mid);
    }
  }

  // Create new Media entity with the current image.
  $media = \Drupal::entityTypeManager()
    ->getStorage('media')
    ->create([
      'bundle' => 'image',
      'uid' => 1,
    ]);

  $media->set('field_media_image', $image);
  $media->save();

  if ($hash) {
    // Store the media id and hash of the image file.
    $hashes[$media->id()] = $hash;
    \Drupal::state()
      ->set('image_field_to_media.hashes_of_image_files', $hashes);
  }

  return $media;
}

/**
 * Batch finish callback.
 */
function image_field_to_media_batch_finished($success, $results, $operations): void {
  if ($success) {
    $message = t('Cloned successfully! Updated entities: @number.', ['@number' => $results['processed']]);
    \Drupal::messenger()->addStatus($message);
  }
  else {
    \Drupal::messenger()->addError(t('Finished with an error.'));
  }
}
