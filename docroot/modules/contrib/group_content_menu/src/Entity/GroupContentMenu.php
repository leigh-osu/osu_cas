<?php

namespace Drupal\group_content_menu\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group_content_menu\GroupContentMenuInterface;

/**
 * Defines the group content menu entity class.
 *
 * @ContentEntityType(
 *   id = "group_content_menu",
 *   label = @Translation("Group content menu"),
 *   label_collection = @Translation("Group content menus"),
 *   bundle_label = @Translation("Group content menu type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\group_content_menu\GroupContentMenuListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\group_content_menu\Form\GroupContentMenuForm",
 *       "edit" = "Drupal\group_content_menu\Form\GroupContentMenuForm",
 *       "delete" = "Drupal\group_content_menu\Form\GroupContentMenuDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\group_content_menu\Routing\GroupContentMenuRouteProvider",
 *     },
 *     "translation" = "Drupal\group_content_menu\Access\GroupMenuItemTranslateAccessHandler"
 *   },
 *   base_table = "group_content_menu",
 *   data_table = "group_content_menu_field_data",
 *   revision_table = "group_content_menu_revision",
 *   revision_data_table = "group_content_menu_field_revision",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "bundle" = "bundle",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *   },
 *   links = {
 *     "add-form" = "/group/{group}/menu/add/{plugin_id}",
 *     "add-page" = "/group/{group}/menu/add",
 *     "add-menu-link" = "/group/{group}/menu/{group_content_menu}/add-link",
 *     "edit-menu-link" = "/group/{group}/menu/{group_content_menu}/link/{menu_link_content}",
 *     "translate-menu-link" = "/group/{group}/menu/{group_content_menu}/link/{menu_link_content}/translations",
 *     "translate-menu-add-link" = "/group/{group}/menu/{group_content_menu}/link/{menu_link_content}/translations/add/{source}/{target}",
 *     "translate-menu-edit-link" = "/group/{group}/menu/{group_content_menu}/link/{menu_link_content}/translations/edit/{language}",
 *     "translate-menu-delete-link" = "/group/{group}/menu/{group_content_menu}/link/{menu_link_content}/translations/delete/{language}",
 *     "delete-menu-link" = "/group/{group}/menu/{group_content_menu}/link/{menu_link_content}/delete",
 *     "content-translation-overview" = "/group/{group}/menu/{group_content_menu}/translations",
 *     "canonical" = "/group/{group}/menu/{group_content_menu}",
 *     "edit-form" = "/group/{group}/menu/{group_content_menu}/edit",
 *     "delete-form" = "/group/{group}/menu/{group_content_menu}/delete",
 *     "collection" = "/group/{group}/menus"
 *   },
 *   bundle_entity_type = "group_content_menu_type",
 *   field_ui_base_route = "entity.group_content_menu_type.edit_form"
 * )
 */
class GroupContentMenu extends ContentEntityBase implements GroupContentMenuInterface, EntityPublishedInterface {

  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields += static::publishedBaseFieldDefinitions($entity_type);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    /** @var \Drupal\group\Entity\GroupRelationshipInterface[] $group_relationships */
    $group_relationships = \Drupal::entityTypeManager()->getStorage('group_content')->loadByEntity($this);
    if ($group_relationship = reset($group_relationships)) {
      // The group is needed as a route parameter.
      $uri_route_parameters['group'] = $group_relationship->getGroup()->id();
      $uri_route_parameters['group_content_menu_type'] = $group_relationship->getEntity()->bundle();
    }

    return $uri_route_parameters;
  }

}
