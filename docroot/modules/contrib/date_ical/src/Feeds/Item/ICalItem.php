<?php

namespace Drupal\date_ical\Feeds\Item;

use Drupal\feeds\Feeds\Item\BaseItem;

/**
 * Defines an item class for use with an iCal document.
 */
class ICalItem extends BaseItem {

  /**
   * Datetime stamp.
   *
   * @var string timestamp
   */
  protected $dtstamp;

  /**
   * Summary.
   *
   * @var string summary
   */
  protected $summary;

  /**
   * Comment.
   *
   * @var string comment
   */
  protected $comment;

  /**
   * Description.
   *
   * @var string description
   */
  protected $description;

  /**
   * Datetime start.
   *
   * @var string timestamp
   */
  protected $dtstart;

  /**
   * Datetime end.
   *
   * @var string timestamp
   */
  protected $dtend;

  /**
   * Universal id.
   *
   * @var string uid
   */
  protected $uid;

  /**
   * Url uri.
   *
   * @var string url
   */
  protected $url;

  /**
   * Location.
   *
   * @var string location
   */
  protected $location;

  /**
   * Categories.
   *
   * @var string|array categories
   */
  protected $categories;

  /**
   * Status.
   *
   * @var string status
   */
  protected $status;

  /**
   * Geolocation.
   *
   * @var array geo
   */
  protected $geo;

  /**
   * Organize.
   *
   * @var string organizer
   */
  protected $organizer;

  /**
   * Attendee.
   *
   * @var string|array attendee
   */
  protected $attendee;

  /**
   * Alarm.
   *
   * @var string alarm
   */
  protected $alarm;

  /**
   * Attach.
   *
   * @var string attach
   */
  protected $attach;

  /**
   * Datetime create.
   *
   * @var string timestamp
   */
  protected $created;

  /**
   * Datetime changed.
   *
   * @var string timestamp
   */
  protected $lastmodified;

}
