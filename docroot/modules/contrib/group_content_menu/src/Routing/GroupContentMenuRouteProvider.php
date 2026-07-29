<?php

declare(strict_types=1);

namespace Drupal\group_content_menu\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\group_content_menu\Entity\GroupContentMenuType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for group_content_menu content.
 */
class GroupContentMenuRouteProvider extends DefaultHtmlRouteProvider {

  public function __construct(
    $entity_type_manager,
    $entity_field_manager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($entity_type_manager, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    if ($add_menu_link = $this->getAddMenuLink($entity_type)) {
      $collection->add('entity.group_content_menu.add_menu_link', $add_menu_link);
    }
    if ($edit_menu_link = $this->getEditMenuLink($entity_type)) {
      $collection->add('entity.group_content_menu.edit_menu_link', $edit_menu_link);
    }
    if ($translation_menu_overview = $this->getTranslationMenuOverview($entity_type)) {
      $collection->add('entity.group_content_menu.content-translation-overview', $translation_menu_overview);
    }
    if ($delete_menu_link = $this->getDeleteMenuLink($entity_type)) {
      $collection->add('entity.group_content_menu.delete_menu_link', $delete_menu_link);
    }
    if ($translations_menu_link = $this->getTranslationMenuItemLink($entity_type)) {
      $collection->add('entity.group_content_menu.translate_menu_link', $translations_menu_link);
    }
    if ($translations_menu_add_link = $this->getTranslationMenuItemAddLink($entity_type)) {
      $collection->add('entity.group_content_menu.translate_menu_add_link', $translations_menu_add_link);
    }
    if ($translations_menu_edit_link = $this->getTranslationMenuItemEditLink($entity_type)) {
      $collection->add('entity.group_content_menu.translate_menu_edit_link', $translations_menu_edit_link);
    }
    if ($translations_menu_edit_link = $this->getTranslationMenuItemDeleteLink($entity_type)) {
      $collection->add('entity.group_content_menu.translate_menu_delete_link', $translations_menu_edit_link);
    }

    return $collection;
  }

  /**
   * Gets the add-menu-link route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getAddMenuLink(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('add-menu-link')) {
      $route = new Route($entity_type->getLinkTemplate('add-menu-link'));
      return $route
        ->setDefaults([
          '_title' => 'Add menu link',
          '_controller' => 'Drupal\group_content_menu\Controller\GroupContentMenuController::addLink',
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu items')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
        ])
        ->setOption('_group_operation_route', TRUE);
    }
  }

  /**
   * Gets the edit-menu-link route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getEditMenuLink(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('edit-menu-link')) {
      $route = new Route($entity_type->getLinkTemplate('edit-menu-link'));
      return $route
        ->setDefaults([
          '_title' => 'Edit menu link',
          '_controller' => 'Drupal\group_content_menu\Controller\GroupContentMenuController::editLink',
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu items')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
          'menu_link_content' => ['type' => 'entity:menu_link_content'],
        ])
        ->setOption('_group_operation_route', TRUE);
    }
  }

  /**
   * Gets the delete-menu-link route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getDeleteMenuLink(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('delete-menu-link')) {
      $route = new Route($entity_type->getLinkTemplate('delete-menu-link'));
      return $route
        ->setDefaults([
          '_title' => 'Delete menu link',
          '_controller' => 'Drupal\group_content_menu\Controller\GroupContentMenuController::deleteLink',
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu items')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
          'menu_link_content' => ['type' => 'entity:menu_link_content'],
        ])
        ->setOption('_group_operation_route', TRUE);
    }
  }

  /**
   * Gets the content-translation-overview route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getTranslationMenuOverview(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('content-translation-overview') && $this->moduleHandler->moduleExists('content_translation')) {
      $route = new Route($entity_type->getLinkTemplate('content-translation-overview'));
      return $route
        ->setDefaults([
          '_title' => 'Translation overview',
          '_controller' => '\Drupal\content_translation\Controller\ContentTranslationController::overview',
          'entity_type_id' => $entity_type->id(),
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu item translations')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setRequirement('_access', 'TRUE')
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          $entity_type->id() => [
            'type' => 'entity:' . $entity_type->id(),
            'load_latest_revision' => TRUE,
          ],
        ])
        ->setOption('_group_operation_route', TRUE)
        ->setOption('_admin_route', TRUE);
    }
  }

  /**
   * Gets the translate-menu-link route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getTranslationMenuItemLink(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('translate-menu-link') && $this->moduleHandler->moduleExists('content_translation')) {
      $route = new Route($entity_type->getLinkTemplate('translate-menu-link'));
      return $route
        ->setDefaults([
          '_title' => 'Translation overview',
          '_controller' => 'Drupal\group_content_menu\Controller\GroupContentMenuTranslationController::overview',
          'entity_type_id' => 'menu_link_content',
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu item translations')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setRequirement('_access', 'TRUE')
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
          'menu_link_content' => [
            'type' => 'entity:menu_link_content',
            'load_latest_revision' => TRUE,
          ],
        ])
        ->setOption('_group_operation_route', TRUE)
        ->setOption('_admin_route', TRUE);
    }
  }

  /**
   * Gets the translate-menu-add-link route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getTranslationMenuItemAddLink(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('translate-menu-add-link') && $this->moduleHandler->moduleExists('content_translation')) {
      $route = new Route($entity_type->getLinkTemplate('translate-menu-add-link'));
      return $route
        ->setDefaults([
          '_title' => 'Add translation',
          '_controller' => '\Drupal\content_translation\Controller\ContentTranslationController::add',
          'entity_type_id' => 'menu_link_content',
          'source' => NULL,
          'target' => NULL,
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu item translations')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setRequirement('_access', 'TRUE')
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
          'menu_link_content' => [
            'type' => 'entity:menu_link_content',
            'load_latest_revision' => TRUE,
          ],
          'source' => ['type' => 'language'],
          'target' => ['type' => 'language'],
        ])
        ->setOption('_group_operation_route', TRUE)
        ->setOption('_admin_route', TRUE);
    }
  }

  /**
   * Gets the translate-menu-edit-link route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getTranslationMenuItemEditLink(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('translate-menu-edit-link') && $this->moduleHandler->moduleExists('content_translation')) {
      $route = new Route($entity_type->getLinkTemplate('translate-menu-edit-link'));
      return $route
        ->setDefaults([
          '_title' => 'Edit translation',
          '_controller' => '\Drupal\content_translation\Controller\ContentTranslationController::edit',
          'entity_type_id' => 'menu_link_content',
          'language' => NULL,
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu item translations')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
          'menu_link_content' => [
            'type' => 'entity:menu_link_content',
            'load_latest_revision' => TRUE,
          ],
          'language' => ['type' => 'language'],
        ])
        ->setOption('_group_operation_route', TRUE)
        ->setOption('_admin_route', TRUE);
    }
  }

  /**
   * Gets the translate-menu-delete-link route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getTranslationMenuItemDeleteLink(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('translate-menu-delete-link') && $this->moduleHandler->moduleExists('content_translation')) {
      $route = new Route($entity_type->getLinkTemplate('translate-menu-delete-link'));
      return $route
        ->setDefaults([
          '_title' => 'Delete translation',
          '_entity_form' => 'menu_link_content.content_translation_deletion',
          'entity_type_id' => 'menu_link_content',
          'language' => NULL,
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu menu item translations')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
          'menu_link_content' => [
            'type' => 'entity:menu_link_content',
            'load_latest_revision' => TRUE,
          ],
          'language' => ['type' => 'language'],
        ])
        ->setOption('_group_operation_route', TRUE)
        ->setOption('_admin_route', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddPageRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getAddPageRoute($entity_type)) {
      $requirements = $route->getRequirements();
      // Remove entity access requirement since we only want group permissions,
      // not some global user permission.
      unset($requirements['_entity_create_any_access']);
      $route->setRequirements($requirements);
      return $route
        ->setDefaults([
          '_title' => 'Add new menu',
          '_controller' => 'Drupal\group_content_menu\Controller\GroupContentMenuController::addPage',
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
          'menu_link_content' => ['type' => 'entity:menu_link_content'],
        ])
        ->setOption('_group_operation_route', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getAddFormRoute($entity_type)) {
      $requirements = $route->getRequirements();
      // Remove entity access requirement since we only want group permissions,
      // not some global user permission.
      unset($requirements['_entity_create_access']);
      $route->setRequirements($requirements);
      return $route
        ->setDefaults([
          '_title' => 'Add new menu',
          '_controller' => 'Drupal\group_content_menu\Controller\GroupContentMenuController::createForm',
        ])
        ->setRequirement('_group_permission', 'manage group_content_menu')
        ->setRequirement('_group_installed_content', implode('+', $this->getPluginIds()))
        ->setOption('_group_operation_route', TRUE);
    }
  }

  /**
   * Gets the collection route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|void
   *   The generated route, if available.
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('collection') && $entity_type->hasListBuilderClass()) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
      $label = $entity_type->getCollectionLabel();
      $route = new Route($entity_type->getLinkTemplate('collection'));
      return $route
        ->addDefaults([
          '_entity_list' => $entity_type->id(),
          '_title' => $label->getUntranslatedString(),
          '_title_arguments' => $label->getArguments(),
          '_title_context' => $label->getOption('context'),
        ])
        ->setOption('_group_operation_route', TRUE)
        ->setRequirement('_group_permission', 'access group content menu overview')
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
        ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getCanonicalRoute($entity_type)) {
      $requirements = $route->getRequirements();
      // Remove entity access requirement since we only want group permissions,
      // not some global user permission.
      unset($requirements['_entity_access']);
      $route->setRequirements($requirements);
      return $route
        ->setRequirement('_group_menu_owns_content', 'TRUE')
        ->setRequirement('_group_permission', 'manage group_content_menu')
        ->setOption('_group_operation_route', TRUE)
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
        ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getEditFormRoute($entity_type)) {
      $requirements = $route->getRequirements();
      // Remove entity access requirement since we only want group permissions,
      // not some global user permission.
      unset($requirements['_entity_access']);
      $route->setRequirements($requirements);
      return $route
        ->setRequirement('_group_menu_owns_content', 'TRUE')
        ->setRequirement('_group_permission', 'manage group_content_menu menu items')
        ->setOption('_group_operation_route', TRUE)
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
        ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getDeleteFormRoute($entity_type)) {
      $requirements = $route->getRequirements();
      // Remove entity access requirement since we only want group permissions,
      // not some global user permission.
      unset($requirements['_entity_access']);
      $route->setRequirements($requirements);
      return $route
        ->setRequirement('_group_menu_owns_content', 'TRUE')
        ->setRequirement('_group_permission', 'manage group_content_menu')
        ->setOption('_group_operation_route', TRUE)
        ->setOption('parameters', [
          'group' => ['type' => 'entity:group'],
          'group_content_menu' => ['type' => 'entity:group_content_menu'],
        ]);
    }
  }

  /**
   * Get plugin IDs.
   *
   * @return array
   *   The plugin IDs.
   */
  protected function getPluginIds(): array {
    $plugin_ids = [];
    foreach (array_keys(GroupContentMenuType::loadMultiple()) as $entity_type_id) {
      $plugin_ids[] = "group_content_menu:$entity_type_id";
    }
    return $plugin_ids ?: ['group_content_menu'];
  }

}
