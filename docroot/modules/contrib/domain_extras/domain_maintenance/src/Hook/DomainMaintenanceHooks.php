<?php

namespace Drupal\domain_maintenance\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Drupal\domain_maintenance\Service\DomainMaintenanceMode;

/**
 * Hook implementations for domain_maintenance.
 */
class DomainMaintenanceHooks {

  use StringTranslationTrait;

  /**
   * Constructs a DomainMaintenanceHooks object.
   *
   * @param \Drupal\domain_config_ui\DomainConfigUIManagerInterface $domainConfigUiManager
   *   The domain config UI manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    protected DomainConfigUIManagerInterface $domainConfigUiManager,
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_system_site_maintenance_mode_alter')]
  public function formSystemSiteMaintenanceModeAlter(
    array &$form,
    FormStateInterface $form_state,
    $form_id,
  ): void {
    $active_domain_id = $this->domainConfigUiManager
      ->getActiveDomainId();
    if (!empty($active_domain_id)
      && $this->domainConfigUiManager
        ->isRegisteredConfiguration('system.maintenance')
    ) {
      $domain_state_name = 'domain.' . $active_domain_id
        . '.system.maintenance_mode';
      $form['maintenance_mode']['#default_value'] = (bool) $this
        ->state->get($domain_state_name);
      $form['maintenance_mode']['#description'] .= ' '
        . $this->t('This setting is currently overridden by the Domain module.');
      $form['#validate'][] = [self::class, 'validateForm'];
      $form['#submit'][] = [self::class, 'submitForm'];
    }
  }

  /**
   * Form validate handler for the maintenance mode form.
   */
  public static function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    /** @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface $manager */
    $manager = \Drupal::service('domain_config_ui.manager');
    $active_domain_id = $manager->getActiveDomainId();
    if (!empty($active_domain_id)
      && $manager->isRegisteredConfiguration('system.maintenance')
    ) {
      $domain_maintenance_mode = (bool) $form_state
        ->getValue('maintenance_mode');
      $form_state->setValue(
        'domain_maintenance_mode',
        $domain_maintenance_mode
      );
      $base_maintenance_mode = (bool) \Drupal::service('state')
        ->get('system.maintenance_mode');
      $form_state->setValue(
        'maintenance_mode',
        $base_maintenance_mode
      );
    }
  }

  /**
   * Form submit handler for the maintenance mode form.
   */
  public static function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    /** @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface $manager */
    $manager = \Drupal::service('domain_config_ui.manager');
    $active_domain_id = $manager->getActiveDomainId();
    if (!empty($active_domain_id)
      && $manager->isRegisteredConfiguration('system.maintenance')
    ) {
      $domain_maintenance_mode = (bool) $form_state
        ->getValue('domain_maintenance_mode');
      $domain_state_name = DomainMaintenanceMode::getStateName(
        $active_domain_id
      );
      \Drupal::service('state')->set(
        $domain_state_name,
        $domain_maintenance_mode
      );
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for domain entities.
   */
  #[Hook('domain_delete')]
  public function domainDelete(EntityInterface $domain): void {
    $this->state->delete(
      DomainMaintenanceMode::getStateName($domain->id())
    );
  }

}
