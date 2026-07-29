<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the module's config/settings admin page.
 */
class SitewideAlertConfigForm extends ConfigFormBase {

  /**
   * SitewideAlertConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler for determining which modules are installed.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'sitewide_alert.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sitewide_alert_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('sitewide_alert.settings');

    // Config options change if block submodule is enabled.
    $block_submodule_enabled = $this->moduleHandler->moduleExists('sitewide_alert_block');

    if ($block_submodule_enabled) {
      $this->messenger()->addMessage(
        $this->t(
          'The Sitewide Alert Block submodule is enabled. Make sure to <a href="@block_link">configure Sitewide Alert block placement within the theme(s)</a>. Note that conditional visibility of Sitewide Alert blocks depends on both block visibility and the visibility configured below. In most cases, the block should be set to always be visible and any visibility conditions configured when creating or editing each Sitewide Alert.',
          ['@block_link' => Url::fromRoute('block.admin_display')->toString()]
        )
      );
    }

    $form['show_on_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show on Administration Pages'),
      '#description' => $this->t('This will allow the alerts to show on backend admin pages as well as the frontend.'),
      '#default_value' => $config->get('show_on_admin'),
      '#access' => !$block_submodule_enabled,
    ];

    $form['alert_styles'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Available alert styles'),
      '#default_value' => $config->get('alert_styles'),
      '#description' => '<p>' . $this->t(
          'Enter the list of key|value pairs of alert styles separated by new line, where key is the alert style class name without prefix, and the value is displayed to the alert editor. <br/><strong>For example:</strong><ul><li>To add the class <em>alert-info</em>, use <code>info|Info</code></li><li>To add the class <em>alert-danger</em>, use <code>danger|Very Important</code></li></ul><strong>Warning!</strong> Pre-existing values will be reset.'
      ) . '<br><br></p>',
    ];

    $form['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show alerts count'),
      '#default_value' => $config->get('show_count'),
      '#description' => $this->t("Display 'x out of y alerts' message next to each alert."),
    ];

    $form['display_order'] = [
      '#type' => 'select',
      '#options' => [
        'ascending' => $this->t('Display newer alerts last'),
        'descending' => $this->t('Display newer alerts first'),
      ],
      '#title' => $this->t('Display Order'),
      '#default_value' => $config->get('display_order'),
      '#description' => $this->t('The order that the alerts display on the page when there are multiple active alerts.'),
    ];

    $form['automatic_refresh'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically Update (Refresh) Alerts'),
      '#default_value' => $config->get('automatic_refresh'),
      '#description' => $this->t('If enabled, the browser will periodically check and display any added, removed, or updated alerts without requiring the visitor to refresh the page. This is recommend for time sensitive alerts. When disabled, alerts are only updated once per page view.'),
    ];

    $form['refresh_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Browser Refresh Interval (in seconds)'),
      '#default_value' => $config->get('refresh_interval'),
      '#description' => $this->t('How often should an open page request information on any new or changed alerts. If you have a good full page / reverse proxy caching strategy in effect, this can be set this to a low number (5-15 seconds) to have a more of an "immediate" update. If you do not have a good caching strategy in place, or most of your traffic is authenticated and can\'t be cached, a larger time (60 or 120 seconds) may be warranted to reduce a potential performance impact on the web server.'),
      '#states' => [
        'visible' => [
          ':input[name="automatic_refresh"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['cache_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Browser and Shared Cache Maximum Age (in seconds)'),
      '#description' => $this->t('The maximum time that alerts should be cached by the browser and shared caches (reverse proxies, like Varnish). Increasing the maximum cache age may reduce the volume of requests to the Drupal site, but will increase the amount of time before new and changed alerts show.'),
      '#default_value' => $config->get('cache_max_age'),
    ];

    $form['server_side_render'] = [
      '#type' => 'checkbox',
      '#title' => $this->t(
        'Render alerts server-side (reduces layout shift) - Experimental'
      ),
      '#description' => $this->t(
        'When enabled, alerts appear immediately, reducing layout shift because they are rendered in the HTML response. The trade-off is that page cache becomes dependent on alert content. Adding, editing, or removing an alert will invalidate cached pages site-wide. When disabled (default), alerts load via JavaScript after page load, keeping page cache independent of alerts.<br><br><strong>Experimental:</strong> This feature has not been confirmed to work with all caching configurations (reverse proxies, Varnish, Nginx, CDNs, etc.). Please test thoroughly with your specific setup before using in production. See the module\'s README.md for more details. If you run into issues, report them in the <a href="@issueQueueUrl" target="_blank">issue queue</a>.',
        [
          '@issueQueueUrl' => 'https://www.drupal.org/project/issues/sitewide_alert',
        ]
      ),
      '#default_value' => $config->get('server_side_render'),
    ];

    $form['show_untranslated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show untranslated alerts'),
      '#default_value' => $config->get('show_untranslated'),
      '#description' => $this->t('When enabled, alerts will display for the current language, regardless of whether the alert has been translated to that language or not. When an alert does not have a translation, the alert will fall back to its original language. When disabled, the site will only show alerts that match the current language.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('cache_max_age') < 0) {
      $form_state->setErrorByName('cache_max_age', $this->t('Browser and Shared Cache Maximum Age (in seconds) can not be negative'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->config('sitewide_alert.settings')
      ->set('show_on_admin', $form_state->getValue('show_on_admin'))
      ->set('alert_styles', $form_state->getValue('alert_styles'))
      ->set('show_count', $form_state->getValue('show_count'))
      ->set('display_order', $form_state->getValue('display_order'))
      ->set('refresh_interval', $form_state->getValue('refresh_interval'))
      ->set('automatic_refresh', $form_state->getValue('automatic_refresh'))
      ->set('cache_max_age', $form_state->getValue('cache_max_age'))
      ->set('server_side_render', $form_state->getValue('server_side_render'))
      ->set('show_untranslated', $form_state->getValue('show_untranslated'))
      ->save();
  }

}
