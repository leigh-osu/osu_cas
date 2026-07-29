<?php

declare(strict_types=1);

namespace Drupal\date_ap_style\Form;

use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration for the default AP Style Date settings.
 */
class DateAPStyleSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'date_ap_style.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'date_ap_style_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('These are default settings for AP style formatters. They only apply when you us
e the AP style field formatters or Twig extension.'),
    ];

    $form['always_display_year'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always display year'),
      '#description' => $this->t('When unchecked, the year will not be displayed if the date is in the same year as the current date.'),
      '#config_target' => 'date_ap_style.settings:always_display_year',
    ];
    $form['use_today'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use today'),
      '#config_target' => 'date_ap_style.settings:use_today',
    ];
    $form['cap_today'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capitalize today'),
      '#config_target' => 'date_ap_style.settings:cap_today',
      '#states' => [
        'visible' => [
          ':input[name="use_today"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['display_day'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display day of the week'),
      '#config_target' => 'date_ap_style.settings:display_day',
      '#description' => $this->t('Displays the day of the week (e.g., Monday) if the date falls within the same week as the current date.'),
      '#states' => [
        'visible' => [
          ':input[name="month_only"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['month_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Month Only'),
      '#config_target' => 'date_ap_style.settings:month_only',
      '#description' => $this->t('Shows only the month (e.g., Aug.) for the date, excluding the day and year.'),
      '#states' => [
        'visible' => [
          ':input[name="always_display_year"]' => ['checked' => FALSE],
          ':input[name="use_today"]' => ['checked' => FALSE],
          ':input[name="display_day"]' => ['checked' => FALSE],
          ':input[name="display_time"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['display_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display time'),
      '#config_target' => 'date_ap_style.settings:display_time',
      '#states' => [
        'visible' => [
          ':input[name="month_only"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['hide_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide date'),
      '#description' => $this->t('When checked, the date will not be displayed.'),
      '#config_target' => 'date_ap_style.settings:hide_date',
      '#states' => [
        'visible' => [
          ':input[name="display_time"]' => ['checked' => TRUE],
          ':input[name="always_display_year"]' => ['checked' => FALSE],
          ':input[name="month_only"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['time_before_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display time before date'),
      '#description' => $this->t('When checked, the time will be displayed before the date. Otherwise it will be displayed after the date.'),
      '#config_target' => 'date_ap_style.settings:time_before_date',
      '#states' => [
        'visible' => [
          ':input[name="display_time"]' => ['checked' => TRUE],
          ':input[name="hide_date"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['display_noon_and_midnight'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display noon and midnight'),
      '#description' => $this->t('Converts 12:00 p.m. to &quot;noon&quot; and 12:00 a.m. to &quot;midnight.&quot;'),
      '#config_target' => 'date_ap_style.settings:display_noon_and_midnight',
      '#states' => [
        'visible' => [
          ':input[name="display_time"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['capitalize_noon_and_midnight'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capitalize noon and midnight'),
      '#config_target' => 'date_ap_style.settings:capitalize_noon_and_midnight',
      '#states' => [
        'visible' => [
          ':input[name="display_time"]' => ['checked' => TRUE],
          ':input[name="display_noon_and_midnight"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['use_all_day'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show "All Day" instead of midnight'),
      '#config_target' => 'date_ap_style.settings:use_all_day',
      '#states' => [
        'visible' => [
          ':input[name="display_time"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Date range separator'),
      '#options' => [
        'to' => $this->t('to'),
        'hyphen' => $this->t('Hyphen'),
        'endash' => $this->t('En dash'),
      ],
      '#config_target' => 'date_ap_style.settings:separator',
    ];
    $form['timezone'] = [
      '#type' => 'select',
      '#title' => $this->t('Time zone'),
      '#options' => ['' => $this->t('- Default site/user time zone -')] + TimeZoneFormHelper::getOptionsList(),
      '#size' => 1,
      '#config_target' => 'date_ap_style.settings:timezone',
    ];
    return parent::buildForm($form, $form_state);
  }

}
