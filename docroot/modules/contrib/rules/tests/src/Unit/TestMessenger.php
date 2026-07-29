<?php

declare(strict_types=1);

namespace Drupal\Tests\rules\Unit;

use Drupal\Core\Messenger\MessengerInterface;

/**
 * Mock class to replace the messenger service in unit tests.
 */
class TestMessenger implements MessengerInterface {

  /**
   * Array of messages.
   *
   * @var array
   */
  protected $messages = [];

  /**
   * {@inheritdoc}
   */
  public function addMessage($message, $type = self::TYPE_STATUS, $repeat = FALSE) {
    if (!empty($message)) {
      $this->messages[$type] = $this->messages[$type] ?? [];
      if ($repeat || !in_array($message, $this->messages[$type])) {
        $this->messages[$type][] = $message;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addStatus($message, $repeat = FALSE) {
    return $this->addMessage($message, static::TYPE_STATUS, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addError($message, $repeat = FALSE) {
    return $this->addMessage($message, static::TYPE_ERROR, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addWarning($message, $repeat = FALSE) {
    return $this->addMessage($message, static::TYPE_WARNING, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function all() {
    return $this->messages;
  }

  /**
   * {@inheritdoc}
   */
  public function messagesByType($type) {
    if (!empty($type)) {
      return $this->messages[$type] ?? [];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $return = $this->messages;
    $this->messages = [];
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByType($type) {
    if (!empty($type) && isset($this->messages[$type])) {
      $return = $this->messages[$type];
      $this->messages[$type] = [];
      return $return;
    }
    return [];
  }

}
