<?php

namespace Drupal\shurly;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ShurlySubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['shurlyOnRespond', 0];
    return $events;
  }

  public function shurlyOnRespond(RequestEvent $event) {
    $path = \Drupal::service('path.current')->getPath();
    $current_path = str_replace("/", "", $path);

    if (shurly_validate_custom($current_path)) {
      $row = \Drupal::database()
        ->query("SELECT rid, destination, active FROM {shurly} WHERE source = :q ", [':q' => $current_path])
        ->fetchObject();

      if ($row) {
        if ($row->active == 1) {
          $this->shurlyRedirectTo($row);
        }
        else {
          // Check if the redirect page is defined. Otherwise the request will
          // be handled normally by drupal.
          $shurly_redirect_url = trim(\Drupal::config('shurly.settings')->get('shurly_redirect_page'));
          if (!empty($shurly_redirect_url)) {
            $url = Url::fromUserInput($shurly_redirect_url);
            $response = new RedirectResponse($url->toString());
            $response->send();
            return;
          }
        }
      }
    }
  }

  protected function shurlyRedirectTo($row) {
    \Drupal::moduleHandler()->invokeAll('shurly_redirect_before', [$row]);

    $url = $row->destination;

    $url = str_replace(["\n", "\r"], '', $url);

    session_write_close();

    $response = new RedirectResponse($url);
    $response->send();

    $request_time = \Drupal::time()->getRequestTime();

    \Drupal::database()
      ->query('UPDATE {shurly} SET count = count + 1, last_used = :time WHERE rid = :rid', [
        'time' => $request_time,
        'rid' => $row->rid,
      ]);

    \Drupal::moduleHandler()->invokeAll('shurly_redirect_after', [$row]);

    exit();
  }

}
