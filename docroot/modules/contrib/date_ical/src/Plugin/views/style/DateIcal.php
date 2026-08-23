<?php

namespace Drupal\date_ical\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\date_ical\DateICalInterface;
use Drupal\views\Attribute\ViewsStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Date_ical style plugin.
 *
 * @ingroup views_style_plugins
 */
#[ViewsStyle(
  id: "date_ical",
  title: new TranslatableMarkup("iCal Feed"),
  help: new TranslatableMarkup("View event ical."),
  display_types: ["feed"],
)]
class DateIcal extends StylePluginBase {

  /**
   * Constructs a DateIcal instance.
   *
   * @param array $configuration
   *   The configuration for the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\date_ical\DateICalInterface $dateICal
   *   The iCal service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected readonly DateICalInterface $dateICal, protected ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date_ical.feed'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['cal_name'] = ['default' => ''];
    $options['no_calname'] = ['default' => FALSE];
    $options['disable_webcal'] = ['default' => FALSE];
    $options['exclude_dtstamp'] = ['default' => FALSE];
    $options['unescape_punctuation'] = ['default' => FALSE];
    $options['skip_blank_dates'] = ['default' => TRUE];
    $options['download_directly'] = ['default' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['cal_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('iCal Calendar Name'),
      '#default_value' => $this->options['cal_name'],
      '#description' => $this->t('This will appear as the title of the iCal feed. If left blank, the View Title will be used.
        If that is also blank, the site name will be inserted as the iCal feed title.'),
    ];
    $form['no_calname'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude Calendar Name'),
      '#default_value' => $this->options['no_calname'],
      '#description' => $this->t("Excluding the X-WR-CALNAME value from the iCal Feed causes
        some calendar clients to add the events in the feed to an existing calendar, rather
        than creating a whole new calendar for them."),
    ];
    $form['disable_webcal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable webcal://'),
      '#default_value' => $this->options['disable_webcal'],
      '#description' => $this->t("By default, the feed URL will use the webcal:// scheme, which allows calendar
        clients to easily subscribe to the feed. If you want your users to instead download this iCal
        feed as a file, activate this option."),
    ];
    $form['exclude_dtstamp'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude DTSTAMP'),
      '#default_value' => $this->options['exclude_dtstamp'],
      '#description' => $this->t("By default, the feed will set each event's DTSTAMP property to the time at which the feed got downloaded.
        Some feed readers will (incorrectly) look at the DTSTAMP value when they compare different downloads of the same feed, and
        conclcude that the event has been updated (even though it hasn't actually changed). Enable this option to exclude the DTSTAMP
        field from your feeds, so that these buggy feed readers won't mark every event as updated every time they check."),
    ];
    $form['unescape_punctuation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unescape Commas and Semicolons'),
      '#default_value' => $this->options['unescape_punctuation'],
      '#description' => $this->t('In order to comply with the iCal spec, Date iCal will "escape" commas and semicolons (prepend them with backslashes).
        However, many calendar clients are bugged to not unescape these characters, leaving the backslashes littered throughout your events.
        Enable this option to have Date iCal unescape these characters before it exports the iCal feed.'),
    ];
    $form['additional_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Additional settings'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];
    $form['additional_settings']['skip_blank_dates'] = [
      '#type' => 'checkbox',
      '#parents' => ['style_options', 'skip_blank_dates'],
      '#title' => $this->t('Skip blank dates'),
      '#description' => $this->t('Normally, if a view result has a blank date field, the feed will display an error,
        because it is impossible to create an iCal event with no date. This option makes Views silently skip those results, instead.'),
      '#default_value' => $this->options['skip_blank_dates'],
    ];
    $form['additional_settings']['download_directly'] = [
      '#type' => 'checkbox',
      '#parents' => ['style_options', 'download_directly'],
      '#title' => $this->t('Download directly'),
      '#description' => $this->t('Export the iCal in plain text.'),
      '#default_value' => $this->options['download_directly'],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function attachTo(array &$build, $display_id, Url $path, $title) {
    $url_options = [];
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    $url_options['absolute'] = TRUE;
    if (!empty($this->options['formats'])) {
      $url_options['query']['_format'] = reset($this->options['formats']);
    }

    $url = $path->setOptions($url_options)->toString();

    // If the user didn't disable the option, change the scheme to webcal://
    // so calendar clients can automatically subscribe via the iCal link.
    if (!$this->options['disable_webcal']) {
      $url = str_replace(['http://', 'https://'], 'webcal://', $url);
    }

    $tooltip = !empty($title) ? $title : $this->t('Add this event to my calendar');
    $this->view->feedIcons[] = [
      '#theme' => 'date_ical_icon',
      '#url' => $url,
      '#title' => [
        '#type' => 'inline_template',
        '#template' => $tooltip,
      ],
      '#format' => 'text/calendar',
      '#attributes' => [
        'class' => [
          'ical-icon',
          'feed-icon',
        ],
      ],
    ];
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => 'text/calendar',
      'title' => $tooltip,
      'href' => $url,
    ];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($userAgent, 'Android') !== FALSE) {
      $build["#attached"]["library"][] = 'date_ical/date_ical.android';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $view = $this->view;
    $rowPlugin = $view->rowPlugin;
    if (!is_object($rowPlugin) || !in_array($rowPlugin->getPluginId(), [
      'date_ical',
      'date_ical_fields',
    ])) {
      trigger_error('Drupal\date_ical\Plugin\views\style\DateIcal: Missing row plugin', E_USER_ERROR);
      return $this->t('To enable iCal output, the view Format must be configured to Show: iCal Entity or iCal Fields.');
    }
    $fields = $view->display_handler->getHandlers('field');
    $excludedKeys = array_keys(array_filter($fields, function ($field) {
      return !empty($field->options['exclude']);
    }));
    $excludes = array_keys(array_diff($rowPlugin->options, $excludedKeys));
    $viewDesc = $view->getDisplay()->getOption('display_description');
    $header = [
      'RELCALID' => $view->storage->get('uuid'),
      'TIMEZONE' => date_default_timezone_get(),
      'CALNAME' => !empty($this->options['cal_name']) ? $this->options['cal_name'] : $view->getTitle(),
    ];
    if (!empty($viewDesc)) {
      $header['CALDESC'] = substr($viewDesc, 0, 255);
    }
    $events = [];
    foreach ($view->result as $row_index => $row) {
      $view->row_index = $row_index;
      $vevent = $field = $rowPlugin->render($row);
      if (empty($vevent)) {
        continue;
      }
      if (!empty($this->options['skip_blank_dates']) && empty($field['date_field'])) {
        continue;
      }
      $fieldNotRender = [
        'date_field',
        'end_field',
        'geo_field',
        'organizer_field',
        'attach_field',
        'attendee_field',
      ];
      $field = array_diff_key($field, array_flip($fieldNotRender));
      $fieldNotExclude = array_intersect_key($field, array_flip($excludes));
      // Fallback: If the row style option for description_field is empty, and
      // at least one other field is non-empty, join them.
      if (empty($rowPlugin->options['description_field']) && !empty($fieldNotExclude)) {
        $vevent['description_field'] = implode("\n", $fieldNotExclude);
      }
      // Set uid.
      if (!empty($row->_entity) && method_exists($row->_entity, 'uuid')) {
        $vevent['uuid'] = $row->_entity->uuid();
      }

      $this->moduleHandler->alter('date_ical_export_vevent', $vevent, $this->view, $row);

      $events[] = $vevent;
    }
    $vcalendar = $this->dateICal->feed($events, $header);
    unset($view->row_index);
    $vcalendar = str_replace('kigkonsult.se ', '', $vcalendar);
    // iCalcreator escapes all commas and semicolons in string values, as the
    // spec demands. However, some calendar clients are buggy and fail to
    // unescape these characters. Users may choose to unescape them here to
    // sidestep those clients' bugs.
    // NOTE: This results in a non-compliant iCal feed, but it seems like a
    // LOT of major clients are bugged this way.
    if (!empty($this->options['unescape_punctuation'])) {
      $vcalendar = str_replace('\,', ',', $vcalendar);
      $vcalendar = str_replace('\;', ';', $vcalendar);
    }
    // In order to respect the Exclude DTSTAMP option, we unfortunately have
    // to parse out the DTSTAMP properties after they get rendered. Simply
    // using deleteProperty('DTSTAMP') doesn't work, because iCalcreator
    // considers the DTSTAMP to be essential, and will re-create it when
    // createCalendar() is called.
    if (!empty($this->options['exclude_dtstamp'])) {
      $vcalendar = preg_replace("/^DTSTAMP:.*\r\n/m", "", $vcalendar);
    }

    // These steps shouldn't be run during Preview on the View page.
    if (empty($view->live_preview)) {
      // Prevent devel module from appending queries to ical export.
      $GLOBALS['devel_shutdown'] = FALSE;
      $currentDateTime = new \DateTime();
      $currentDateTime->add(new \DateInterval('P2D'));
      $view->getResponse()->headers->set('Expires', $currentDateTime->format('D, d M Y H:i:s') . ' GMT');
    }
    // Allow other modules to alter the rendered calendar.
    $this->moduleHandler->alter('date_ical_export_post_render', $vcalendar, $view);

    if (!empty($this->options['download_directly'])) {
      $view->getResponse()->headers->set('Content-Type', 'text/calendar; charset=utf-8');
      $view->getResponse()->headers->set('Cache-Control', 'no-cache, must-revalidate');
    }
    else {
      unset($this->view->row_index);
    }
    return [
      '#type' => 'markup',
      '#markup' => Markup::create(Html::decodeEntities($vcalendar)),
    ];
  }

}
