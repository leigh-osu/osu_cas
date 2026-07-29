<?php

namespace Drupal\we_megamenu\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The entity has been pushed successfully.
 * Other modules can use this to react on successful push events.
 */
class AfterConfigSave extends Event {

  const EVENT_NAME = 'we_megamenu.config.save.after';

  /**
   * Menu name.
   *
   * @var string
   */
  protected $menu_name;

  /**
   * Theme.
   *
   * @var string
   */
  protected $theme;

  /**
   * Config Data.
   *
   * @var string
   */
  protected $config_data;

  /**
   * Constructs a entity push event.
   *
   * @param $menu_name
   * @param $theme
   * @param $config_data
   */
  public function __construct($menu_name, $theme, $config_data) {
    $this->menu_name = $menu_name;
    $this->theme = $theme;
    $this->config_data = $config_data;
  }

  /**
   * @return string
   */
  public function getConfigData() {
    return $this->config_data;
  }

  /**
   * @return string
   */
  public function getMenuName() {
    return $this->menu_name;
  }

  /**
   * @return string
   */
  public function getTheme() {
    return $this->theme;
  }

}
