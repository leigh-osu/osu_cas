<?php

namespace Drupal\date_ical;

/**
 * Date iCal interface.
 */
interface DateICalInterface {

  /**
   * Render feed iCal.
   */
  public function feed(array $events, array $header = []);

}
