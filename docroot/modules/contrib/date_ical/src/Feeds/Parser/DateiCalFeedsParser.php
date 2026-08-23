<?php

namespace Drupal\date_ical\Feeds\Parser;

use Drupal\Component\Plugin\Exception\ExceptionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\date_ical\Feeds\Item\ICalItem;
use Drupal\feeds\Component\XmlParserTrait;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Parser\ParserBase;
use Drupal\feeds\Plugin\Type\Parser\ParserInterface;
use Drupal\feeds\Result\FetcherResultInterface;
use Drupal\feeds\Result\ParserResult;
use Drupal\feeds\StateInterface;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an RSS and Atom feed parser.
 *
 * @FeedsParser(
 *   id = "date_ical",
 *   title = @Translation("iCal parser"),
 *   description = @Translation("Parse iCal feeds.")
 * )
 */
class DateiCalFeedsParser extends ParserBase implements ParserInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  // @phpstan-ignore-next-line
  use XmlParserTrait;

  /**
   * Constructs a DateICal instance.
   *
   * @param array $configuration
   *   The configuration for the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, protected ModuleHandlerInterface $moduleHandler) {
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
      $container->get('module_handler'),
    );
  }

  /**
   * Parser iCal.
   *
   * {@inheritDoc}
   */
  public function parse(FeedInterface $feed, FetcherResultInterface $fetcher_result, StateInterface $state) {
    if (!class_exists('Kigkonsult\Icalcreator\Vcalendar')) {
      throw new \RuntimeException('The library "Kigkonsult\Icalcreator" is not installed. You can install it with Composer or by using the Ludwig module.');
    }

    // @phpstan-ignore-next-line
    $result = new ParserResult();
    // Read the iCal feed into memory.
    $ical_feed_contents = $fetcher_result->getRaw();
    if (!strlen(trim($ical_feed_contents))) {
      // @phpstan-ignore-next-line
      throw new EmptyFeedException();
    }
    $baseUrl = \Drupal::request()->getHost();
    $config = [Vcalendar::UNIQUE_ID => $baseUrl];
    // Parse the feed into an iCalcreator vcalendar object.
    $calendar = new vcalendar($config);
    try {
      $calendar->parse($ical_feed_contents);
    }
    catch (ExceptionInterface $e) {
      $args = ['%url' => $feed->label(), '%error' => trim($e->getMessage())];
      throw new \RuntimeException("Parsing the data from $args failed. Please ensure that this URL leads to a valid iCal feed, because of error: " . $e->getMessage());
    }
    $timezone = date_default_timezone_get();
    $dateTimeZone = new \DateTimezone($timezone);
    // Allow modules to alter the vcalendar object before we interpret it.
    $context = [
      'source' => $feed,
      'fetcher_result' => $fetcher_result,
    ];
    $this->moduleHandler->alter('date_ical_import_vcalendar', $calendar, $context);

    // We've got a vcalendar object created from the feed data. Now we need to
    // convert that vcalendar into an array of Feeds-compatible data arrays.
    // ParserVcalendar->parse() does that.
    $config = $feed->getConfigTarget();
    $types = [
      Vcalendar::VEVENT,
      Vcalendar::VTODO,
      Vcalendar::VJOURNAL,
      Vcalendar::VFREEBUSY,
      Vcalendar::VALARM,
    ];
    $total = 0;
    foreach ($types as $type) {
      $vcalendar_components = $calendar->getComponents($type);
      $total += count($vcalendar_components);
      $state->total = $total;
      if (empty($vcalendar_components)) {
        continue;
      }
      // Allow modules to alter the vcalendar component before we parse it
      // into a Feeds-compatible data array.
      $this->moduleHandler->alter('date_ical_import_component', $vcalendar_components, $context2);
      foreach ($vcalendar_components as $vcalendar_component) {
        $event = new ICalItem();
        $isImport = FALSE;
        foreach ($this->getMappingSources() as $field => $handler) {
          $method = $handler['icalcreator_handler'];
          if (is_object($vcalendar_component) && method_exists($vcalendar_component, $method)) {
            $val = $vcalendar_component->$method();
            if ($val instanceof \DateTime) {
              $val->setTimezone($dateTimeZone);
              $val = $val->format(DATE_ATOM);
            }
            $mParse = 'parse' . ucfirst(strtolower(str_replace(':', '', $field)));
            if (method_exists($this, $mParse)) {
              $val = $this->$mParse($val);
            }
            $event->set(strtolower($field), $val);
            $isImport = TRUE;
          }
        }
        if ($isImport) {
          $result->addItem($event);
        }
      }
    }
    // We need to add 1 to the index of the last parsed component so that
    // the subsequent batch starts on the first unparsed component.
    $state->pointer = $total + 1;
    $state->progress($state->total, $state->pointer);

    return $result;
  }

  /**
   * Creates the list of mapping sources offered by DateiCalFeedsParser.
   */
  public function getMappingSources() {
    $sources = [
      'summary' => [
        'label' => $this->t('Summary/Title'),
        'description' => $this->t('The SUMMARY property. A short summary (usually the title) of the event.
          A title is required for every node, so you need to include this source and have it mapped to the node title, except under unusual circumstances.'
        ),
        'icalcreator_handler' => 'getSummary',
      ],
      'comment' => [
        'label' => $this->t('Comment'),
        'description' => $this->t('The COMMENT property. A text comment is allowed on most components.'),
        'icalcreator_handler' => 'getComment',
      ],
      'description' => [
        'label' => $this->t('Description'),
        'description' => $this->t('The DESCRIPTION property. A more complete description of the event than what is provided by the Summary.'),
        'icalcreator_handler' => 'getDescription',
      ],
      'dtstart' => [
        'label' => $this->t('Date: Start'),
        'description' => $this->t('The DTSTART property. The start time of each event in the feed.'),
        'icalcreator_handler' => 'getDtstart',
      ],
      'dtend' => [
        'label' => $this->t('Date: End'),
        'description' => $this->t('THE DTEND or DURATION property. The end time (or duration) of each event in the feed.'),
        'icalcreator_handler' => 'getDtend',
      ],
      'dtstamp' => [
        'label' => $this->t('Datetime stamp'),
        'description' => $this->t('The DTSTAMP property. The date/time that the instance of the iCalendar object was created..'),
        'icalcreator_handler' => 'getDtstamp',
      ],
      'rrule' => [
        'label' => $this->t('Date: Repeat Rule'),
        'description' => $this->t('The RRULE property. Describes when and how often this event should repeat.
          The date field for the target node must be configured to support repeating dates, using the Date Repeat Field module (a submodule of Date).'),
        'icalcreator_handler' => 'getRrule',
      ],
      'uid' => [
        'label' => 'UID',
        'description' => $this->t('The UID property. Each event must have a UID if you wish for the import process to be able to update previously-imported nodes.
          If used, this field MUST be set to Unique.'),
        'icalcreator_handler' => 'getUid',
      ],
      'url' => [
        'label' => 'URL',
        'description' => $this->t('The URL property. Some feeds specify a URL for the event using this property.'),
        'icalcreator_handler' => 'getUrl',
      ],
      'location' => [
        'label' => $this->t('Location'),
        'description' => $this->t('The LOCATION property. Can be mapped to a text field, or the title of a referenced node.'),
        'icalcreator_handler' => 'getLocation',
      ],
      'location:alrep' => [
        'label' => $this->t('Location: ALTREP'),
        'description' => $this->t('The ALTREP value of the LOCATION property. Additional location information, usually a URL to a page with more info.'),
        'icalcreator_handler' => 'getLocation',
      ],
      'categories' => [
        'label' => $this->t('Categories'),
        'description' => $this->t('The CATEGORIES property. Categories that describe the event, which can be imported into taxonomy terms.'),
        'icalcreator_handler' => 'getAllCategories',
      ],
      'organizer' => [
        'label' => $this->t('Organizer'),
        'description' => $this->t('The ORGANIZER property. Organizer that describe the event, which can be imported into user field.'),
        'icalcreator_handler' => 'getOrganizer',
      ],
      'attendee' => [
        'label' => $this->t('Attendee'),
        'description' => $this->t('The ATTENDEE property. Attendee that describe the event, which can be imported into user field.'),
        'icalcreator_handler' => 'getAllAttendee',
      ],
      'duration' => [
        'label' => $this->t('Duration'),
        'description' => $this->t('The DURATION property. Duration that describe the event, which can be imported into duration field'),
        'icalcreator_handler' => 'getDuration',
      ],
      'priority' => [
        'label' => $this->t('Priority'),
        'description' => $this->t('The PRIORITY property. Priority that describe the event, which can be imported into list field integer)'),
        'icalcreator_handler' => 'getPriority',
      ],
      'status' => [
        'label' => $this->t('Status'),
        'description' => $this->t('The STATUS property. Priority that describe the event, which can be imported into list field (TENTATIVE / CONFIRMED / CANCELLED) (NEEDS-ACTION /COMPLETED / IN-PROCESS / CANCELLED) (DRAFT / FINAL/ CANCELLED)'),
        'icalcreator_handler' => 'getStatus',
      ],
      'created' => [
        'label' => $this->t('Created'),
        'description' => $this->t('The CREATED property. Created that describe the event, which can be imported into created.'),
        'icalcreator_handler' => 'getCreated',
      ],
      'last_modified' => [
        'label' => $this->t('Last modified'),
        'description' => $this->t('The LAST-MODIFIED property. Last modified that describe the event, which can be imported into changed.'),
        'icalcreator_handler' => 'getLastmodified',
      ],
      'geo:lat' => [
        'label' => $this->t('Geolocation latitude'),
        'description' => $this->t('The GEO property. Geolocation that describe the event, which can be imported into geo field'),
        'icalcreator_handler' => 'getGeo',
      ],
      'geo:lon' => [
        'label' => $this->t('Geolocation longitude'),
        'description' => $this->t('The GEO property. Geolocation that describe the event, which can be imported into geo field'),
        'icalcreator_handler' => 'getGeo',
      ],
      'contact' => [
        'label' => $this->t('Contact'),
        'description' => $this->t('The CONTACT property. Contact that describe the event, which can be imported into string field'),
        'icalcreator_handler' => 'getAllContact',
      ],
    ];
    return $sources;
  }

  /**
   * Convert geo location latitude value.
   *
   * {@inheritDoc}
   */
  protected function parseGeolat($value) {
    if (!empty($value['latitude'])) {
      return $value['latitude'];
    }
    return '';
  }

  /**
   * Convert geo location longitude value.
   *
   * {@inheritDoc}
   */
  protected function parseGeolon($value) {
    if (!empty($value['longitude'])) {
      return $value['longitude'];
    }
    return '';
  }

  /**
   * Convert email organizer.
   *
   * {@inheritDoc}
   */
  protected function parseOrganizer(string $value) {
    $explode = explode(':', $value);
    foreach ($explode as $email) {
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
      }
    }
    return $value;
  }

  /**
   * Convert email attendee.
   *
   * {@inheritDoc}
   */
  protected function parseAttendee(string|array $value) {
    if (is_string($value)) {
      return $this->parseOrganizer($value);
    }
    foreach ($value as &$val) {
      $val = $this->parseOrganizer($val);
    }
    return $value;
  }

}
