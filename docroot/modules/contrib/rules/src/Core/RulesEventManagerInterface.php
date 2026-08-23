<?php

namespace Drupal\rules\Core;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;

/**
 * Plugin manager for Rules events that can be triggered.
 *
 * Rules events are any Symfony event that is also exposed to by declaring
 * event metadata in MODULE_NAME.rules.events.yml files (placed in the module's
 * base directory. Each event has the following structure:
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     category: STRING
 *     context_definitions:
 *       CONTEXT_NAME:
 *         type: CONTEXT_NAME TYPE
 *         label: CONTEXT_NAME LABEL
 *       ...
 * @endcode
 * For example:
 * @code
 *   rules.user_login:
 *     label: 'User has logged in'
 *     category: 'User'
 *     context_definitions:
 *       account:
 *         type: 'entity:user'
 *         label: 'Logged in user'
 * @endcode
 *
 * @see \Drupal\rules\Context\ContextDefinitionInterface
 * @see \Drupal\rules\Core\RulesEventInterface
 */
interface RulesEventManagerInterface extends CategorizingPluginManagerInterface {

  /**
   * Gets the base name of a configured event name.
   *
   * For a configured event name like {EVENT_NAME}--{SUFFIX}, the base event
   * name {EVENT_NAME} is returned.
   *
   * @param string $event_name
   *   The event name.
   *
   * @return string
   *   The event base name.
   *
   * @see \Drupal\rules\Core\RulesConfigurableEventHandlerInterface::getEventNameSuffix()
   */
  public function getEventBaseName(string $event_name): string;

}
