<?php

declare(strict_types=1);

namespace Drupal\rules\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rules\Logger\RulesDebugLog;
use Psr\Log\LogLevel;

/**
 * Hook implementations used to render debugging information.
 */
final class RulesPageHooks {

  /**
   * Constructs a new RulesPageHooks service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current_user service.
   * @param \Drupal\rules\Logger\RulesDebugLog $rulesDebugLog
   *   The Rule debug logger.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountInterface $currentUser,
    protected RulesDebugLog $rulesDebugLog,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'rules_debug_log_element' => [
        'render element' => 'element',
        'file' => 'rules.theme.inc',
      ],
    ];
  }

  /**
   * Implements hook_page_top().
   */
  #[Hook('page_top')]
  public function pageTop(array &$page_top): void {
    $markup = $this->rulesDebugLog->render();
    // If debugging is turned off $markup will be empty.
    if (!empty($markup)) {
      if ($this->currentUser->hasPermission('access rules debug')) {
        // Send debug output to the screen.
        $this->messenger->addError($markup);
      }
      // Log debugging information to logger.channel.rules only if the rules
      // system logging setting 'debug_log.system_debug' is enabled.
      // These logs get sent to the system dblog, end up in the Drupal database,
      // and are viewable at /admin/reports/dblog.
      if ($this->configFactory->get('rules.settings')->get('debug_log.system_debug')) {
        \Drupal::service('logger.channel.rules')->log(LogLevel::DEBUG, $markup, []);
      }
    }
    // Remove logs already rendered.
    $this->rulesDebugLog->clearLogs();
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    // We need JavaScript and CSS to render the debug log properly
    // and to provide the expand/collapse functionality.
    if ($this->currentUser->hasPermission('access rules debug')) {
      if ($this->configFactory->get('rules.settings')->get('debug_log.enabled')) {
        $attachments['#attached']['library'][] = 'rules/rules.debug';
      }
    }
  }

}
