<?php

declare(strict_types=1);

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\Entity\PreviewLinkInterface;
use Drupal\preview_link\PreviewLinkExpiry;

/**
 * Helper function to apply logic to entities.
 *
 * @param string $entityTypeClass
 *   The entity type class. The entity type's ID must be an auto-incrementing
 *   positive-only integer.
 * @param array $sandbox
 *   The postupdate sandbox.
 * @param callable $process
 *   A callable to apply for each entity.
 * @param int $iterationSize
 *   Number of entities to process per batch.
 *
 * @phpstan-param class-string<T> $entityTypeClass
 * @phpstan-param callable(T $entity): void $process
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup
 *   The message for the current iteration.
 *
 * @template T of \Drupal\Core\Entity\EntityInterface
 */
function _preview_link_entity_migration(string $entityTypeClass, array &$sandbox, callable $process, int $iterationSize = 50): TranslatableMarkup {
  $sandbox['hwm'] ??= 0;

  $entityTypeRepo = \Drupal::service('entity_type.repository');
  $storage = \Drupal::entityTypeManager()->getStorage($entityTypeRepo->getEntityTypeFromClass($entityTypeClass));

  $all = $storage->getQuery()->accessCheck(FALSE);

  $remainingQuery = (clone $all)
    ->condition($storage->getEntityType()->getKey('id'), $sandbox['hwm'], '>');

  $remaining = (clone $remainingQuery)->count()->execute();
  if ($remaining === 0) {
    $sandbox['#finished'] = 1;
    return new TranslatableMarkup('Finished migration');
  }

  $total = (clone $all)->count()->execute();
  $sandbox['#finished'] = ($total - $remaining) / $total;

  $ids = (clone $remainingQuery)
    ->sort($storage->getEntityType()->getKey('id'), direction: 'ASC')
    ->range(0, $iterationSize)
    ->execute();

  foreach ($storage->loadMultiple($ids) as $entity) {
    $sandbox['hwm'] = $entity->id();
    $process($entity);
  }

  $storage->resetCache($ids);

  return new TranslatableMarkup('Processed @entity_type_id entities: @ids', [
    '@ids' => implode(', ', $ids),
    '@entity_type_id' => $storage->getEntityTypeId(),
  ]);
}

/**
 * Sets expiry relative to generated time.
 */
function preview_link_post_update_0001(array &$sandbox): TranslatableMarkup {
  $config = \Drupal::configFactory()->get('preview_link.settings');
  $lifetime = (int) ($config->get('expiry_seconds') ?: PreviewLinkExpiry::DEFAULT_EXPIRY_SECONDS);

  return \_preview_link_entity_migration(
    PreviewLink::class,
    $sandbox,
    process: static function (PreviewLinkInterface $entity) use ($lifetime) {
      $entity
        ->setExpiry(new \DateTimeImmutable('@' . ($entity->getGeneratedTimestamp() + $lifetime)))
        ->save();
    },
  );
}

/**
 * Fix the configuration, if necessary.
 */
function preview_link_post_update_0002(): TranslatableMarkup {
  $config = \Drupal::configFactory()->getEditable('preview_link.settings');

  $enabled_entity_types = $config->get('enabled_entity_types');
  if (!is_array($enabled_entity_types)) {
    $config->set('enabled_entity_types', []);
    $config->save();
    return t('Fixed the enabled_entity_types setting.');
  }
  else {
    return t('No changes were necessary.');
  }
}
