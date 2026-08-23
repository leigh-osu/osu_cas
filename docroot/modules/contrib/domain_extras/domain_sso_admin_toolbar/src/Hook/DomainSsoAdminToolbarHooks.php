<?php

namespace Drupal\domain_sso_admin_toolbar\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\domain\DomainNegotiationContext;

/**
 * Hook implementations for domain_sso_admin_toolbar.
 */
class DomainSsoAdminToolbarHooks {

  use StringTranslationTrait;

  /**
   * Constructs a DomainSsoAdminToolbarHooks object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\domain\DomainNegotiationContext $domainNegotiationContext
   *   The domain negotiation context.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DomainNegotiationContext $domainNegotiationContext,
  ) {}

  /**
   * Implements hook_toolbar_alter().
   */
  #[Hook('toolbar_alter')]
  public function toolbarAlter(array &$items): void {
    if (!$this->currentUser->isAuthenticated()
      || !$this->currentUser->hasPermission('access toolbar')
      || !$this->currentUser->hasPermission(
        'use domain sso admin toolbar'
      )
    ) {
      return;
    }

    $domain_storage = $this->entityTypeManager
      ->getStorage('domain');
    $all_domains = $domain_storage->loadMultipleSorted();

    $domains = array_filter($all_domains, function ($domain) {
      return $domain->status();
    });

    if (count($domains) <= 1) {
      return;
    }

    $current_domain = $this->domainNegotiationContext
      ->getDomain();

    $links = [];
    foreach ($domains as $domain) {
      $domain_id = $domain->id();
      $domain_name = $domain->label();
      $is_current = $current_domain
        && $current_domain->id() === $domain_id;

      $links['domain_sso_' . $domain_id] = [
        'title' => $domain_name . ($is_current ? ' (current)' : ''),
        'url' => Url::fromRoute(
          'domain_sso_admin_toolbar.switch',
          ['domain' => $domain_id]
        ),
        'attributes' => [
          'class' => $is_current ? ['is-active'] : [],
        ],
      ];
    }

    $items['domain_sso_switcher'] = [
      '#type' => 'toolbar_item',
      '#weight' => 999,
      'tab' => [
        '#type' => 'link',
        '#title' => $this->t('Domains'),
        '#url' => Url::fromRoute('<none>'),
        '#attributes' => [
          'title' => $this->t('Switch between domains'),
          'class' => [
            'toolbar-icon',
            'toolbar-icon-domain-sso',
          ],
        ],
      ],
      'tray' => [
        '#heading' => $this->t('Domain Switcher'),
        'domain_links' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['toolbar-menu'],
          ],
          'links' => [
            '#theme' => 'links',
            '#links' => $links,
            '#attributes' => [
              'class' => ['toolbar-menu'],
            ],
          ],
        ],
      ],
      '#attached' => [
        'library' => [
          'domain_sso_admin_toolbar/toolbar_domain_switcher',
        ],
      ],
    ];
  }

}
