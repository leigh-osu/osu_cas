<?php

/**
 * @file
 * Install hooks for group_content_menu module.
 */

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Re-write the menu prefix of all group content menus.
 */
function group_content_menu_post_update_menu_prefix_rewrite(&$sandbox) {
  // If 'progress' is not set, this will be the first run of the batch.
  if (!isset($sandbox['progress'])) {
    $sandbox['ids'] = \Drupal::entityTypeManager()->getStorage('menu_link_content')
      ->getQuery()
      ->condition('menu_name', 'menu_link_content-group-menu-', 'STARTS_WITH')
      ->accessCheck(FALSE)
      ->sort('id', 'ASC')
      ->execute();

    $sandbox['max'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;
  }
  $ids = array_slice($sandbox['ids'], $sandbox['progress'], Settings::get('entity_update_batch_size', 50));
  foreach (MenuLinkContent::loadMultiple($ids) as $menu_link) {
    $id = str_replace('menu_link_content-group-menu-', '', $menu_link->getMenuName());
    $updated_menu_name = GroupContentMenuInterface::MENU_PREFIX . $id;
    $menu_link->set('menu_name', $updated_menu_name);
    // Fix #3144156 as well.
    $menu_link->set('bundle', 'menu_link_content');
    $menu_link->save();
    // After the first menu link, there won't be any more tree entries,
    // so this isn't as bad performance as you would think.
    \Drupal::database()->update('menu_tree')
      ->fields([
        'menu_name' => $updated_menu_name,
      ])
      ->condition('menu_name', $menu_link->getMenuName())
      ->execute();
    $sandbox['progress']++;
  }
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  return t("Updated: @progress out of @max menu links.", ['@progress' => $sandbox['progress'], '@max' => $sandbox['max']]);
}

/**
 * Add additional group role permission to manage menu links.
 */
function group_content_menu_post_update_group_role_permissions(&$sandbox) {
  // If 'progress' is not set, this will be the first run of the batch.
  if (!isset($sandbox['progress'])) {
    $sandbox['ids'] = \Drupal::entityTypeManager()->getStorage('group_role')
      ->getQuery()
      ->condition('permissions.*', 'manage group_content_menu')
      ->accessCheck(FALSE)
      ->sort('id', 'ASC')
      ->execute();

    $sandbox['max'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;
  }
  $ids = array_slice($sandbox['ids'], $sandbox['progress'], Settings::get('entity_update_batch_size', 50));
  foreach (GroupRole::loadMultiple($ids) as $group_role) {
    assert($group_role instanceof GroupRoleInterface);
    $group_role->grantPermission('manage group_content_menu menu items')
      ->save();
    $sandbox['progress']++;
  }
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  return t("Updated: @progress out of @max group roles.", ['@progress' => $sandbox['progress'], '@max' => $sandbox['max']]);
}

/**
 * Update group content menus to be revisionable.
 */
function group_content_menu_post_update_convert_to_revisionable(&$sandbox): string {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('group_content_menu');
  assert($entity_type instanceof ContentEntityTypeInterface);
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('group_content_menu');

  // Update the entity type definition.
  $entity_keys = $entity_type->getKeys();
  $entity_keys['revision'] = 'revision_id';
  $entity_type->set('entity_keys', $entity_keys);
  $entity_type->set('revision_table', 'group_content_menu_revision');
  $entity_type->set('revision_data_table', 'group_content_menu_field_revision');

  // Update the field storage definitions and add the new ones required by a
  // revisionable and translatable entity type.
  $field_storage_definitions['label']->setRevisionable(TRUE);
  $field_storage_definitions['langcode']->setRevisionable(TRUE);

  $field_storage_definitions['revision_id'] = BaseFieldDefinition::create('integer')
    ->setName('revision_id')
    ->setTargetEntityTypeId('group_content_menu')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision ID'))
    ->setReadOnly(TRUE)
    ->setSetting('unsigned', TRUE);

  $field_storage_definitions['revision_default'] = BaseFieldDefinition::create('boolean')
    ->setName('revision_default')
    ->setTargetEntityTypeId('group_content_menu')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Default revision'))
    ->setDescription(new TranslatableMarkup('A flag indicating whether this was a default revision when it was saved.'))
    ->setStorageRequired(TRUE)
    ->setInternal(TRUE)
    ->setTranslatable(FALSE)
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
    ->setName('revision_translation_affected')
    ->setTargetEntityTypeId('group_content_menu')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision translation affected'))
    ->setDescription(new TranslatableMarkup('Indicates if the last edit of a translation belongs to current revision.'))
    ->setReadOnly(TRUE)
    ->setRevisionable(TRUE)
    ->setTranslatable(TRUE);

  // This should've been handled automatically by Workspaces, but it's not due
  // to a static caching problem, so we have to do it manually.
  if (\Drupal::moduleHandler()->moduleExists('workspaces')) {
    $entity_type->setRevisionMetadataKey('workspace', 'workspace');

    $field_storage_definitions['workspace'] = BaseFieldDefinition::create('entity_reference')
      ->setName('workspace')
      ->setTargetEntityTypeId('group_content_menu')
      ->setTargetBundle(NULL)
      ->setLabel(new TranslatableMarkup('Workspace'))
      ->setDescription(new TranslatableMarkup('Indicates the workspace that this revision belongs to.'))
      ->setSetting('target_type', 'workspace')
      ->setInternal(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE);
  }

  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);

  return (string) t('Group Content Menus have been converted to be revisionable.');
}
