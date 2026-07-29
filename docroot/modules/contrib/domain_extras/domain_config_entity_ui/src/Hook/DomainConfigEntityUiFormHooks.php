<?php

namespace Drupal\domain_config_entity_ui\Hook;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageInterface;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Drupal\domain_config_ui\Hook\DomainConfigUiFormHooks;

/**
 * Form hook implementations for domain_config_entity_ui.
 *
 * Exposes the parent module's "Enable domain configuration" toggle on
 * EntityForm-based config entity edit forms (block, view mode, search
 * page, …). The parent's DomainConfigUiFormHooks handles ConfigFormBase
 * flows; this hook adds the EntityForm flow as a sibling, calling into
 * the parent's enableDomainConfigForm() helper so the toggle UI itself
 * stays consistent.
 *
 * The toggle is only exposed for entity types whose storage handler
 * implements DomainAwareConfigEntityStorageInterface — i.e. entity
 * types this submodule has explicitly covered with a swap in
 * DomainConfigEntityUiEntityTypeHooks. Without that gate, the toggle
 * could let a user register a config the read-side cannot safely
 * round-trip, reproducing the silent-override-drop bug from #3587744.
 *
 * Installing this submodule is the opt-in — there is no runtime flag.
 */
class DomainConfigEntityUiFormHooks {

  public function __construct(
    protected DomainConfigUIManagerInterface $manager,
    protected DomainConfigUiFormHooks $parentFormHooks,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_form_alter().
   *
   * Config entities edited through an EntityForm are stored as regular
   * config (block.block.*, view modes, search pages, …).
   * ConfigEntityStorage::doSave() goes through ConfigFactory, so
   * DomainConfigFactory's per-domain override layer applies
   * transparently once the config name is registered for the active
   * domain.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!$this->manager->getActiveDomainId() || !$this->manager->isAllowedRoute()) {
      return;
    }
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityForm) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof ConfigEntityInterface || $entity->isNew()) {
      return;
    }
    // Capability gate: the storage must be domain-aware so that
    // override-free reads downstream return override-merged data and
    // the diff bridge in DomainConfigOverrideEditable::save() lands
    // a correct sparse override row.
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if (!$storage instanceof DomainAwareConfigEntityStorageInterface) {
      return;
    }
    $this->parentFormHooks->enableDomainConfigForm($form, [$entity->getConfigDependencyName()]);
  }

  /**
   * Implements hook_domain_config_ui_disallowed_configurations_alter().
   *
   * The toggle should never be exposed on this submodule's own
   * SettingsForm — the per-entity-type coverage selection is a
   * module-wide concern, not a per-domain value. Without this guard
   * the parent module's form_alter would inject the
   * "Enable domain configuration" toggle on the form because the
   * underlying config name is otherwise allowed.
   */
  #[Hook('domain_config_ui_disallowed_configurations_alter')]
  public function disallowedConfigurationsAlter(array &$disallowed): void {
    $disallowed[] = 'domain_config_entity_ui.settings';
  }

}
