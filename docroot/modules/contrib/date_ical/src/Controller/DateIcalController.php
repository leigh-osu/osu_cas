<?php

declare(strict_types=1);

namespace Drupal\date_ical\Controller;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\date_ical\DateICalInterface;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Date iCal routes.
 */
final class DateIcalController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly DateICalInterface $dateIcalFeed,
    private readonly EntityFieldManagerInterface $entity_field_manager,
    private readonly RendererInterface $renderer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('date_ical.feed'),
      $container->get('entity_field.manager'),
      $container->get('renderer'),
    );
  }

  /**
   * Builds the download.
   *
   * {@inheritdoc}
   */
  public function download(Request $request) {
    $query = $request->query->all();
    $event = [];
    // Only process supported iCal fields.
    $plainTextFields = [
      'summary',
      'description',
      'location',
      'categories',
    ];
    foreach ($plainTextFields as $field) {
      if (!empty($query[$field]) && is_string($query[$field])) {
        // Ensure plain text output.
        $event[$field . '_field'] = PlainTextOutput::renderFromHtml($query[$field]);
      }
    }
    // Validate organizer as an email address.
    if (!empty($query['organizer']) && is_string($query['organizer']) && filter_var($query['organizer'], FILTER_VALIDATE_EMAIL)) {
      $event['organizer_field'] = $query['organizer'];
    }
    // Validate URL format.
    if (!empty($query['url']) && is_string($query['url']) && filter_var($query['url'], FILTER_VALIDATE_URL)) {
      $event['url_field'] = $query['url'];
    }
    if (!empty($query['dtstart']) && is_string($query['dtstart'])) {
      $event['date_field']['value'] = $query['dtstart'];
    }
    if (!empty($query['dtend']) && is_string($query['dtend'])) {
      $event['date_field']['end_value'] = $query['dtend'];
    }
    $events = [$event];
    $header['CALNAME'] = $event['summary_field'] ?? $this->t('Event');
    try {
      $vcalendar = $this->dateIcalFeed->feed($events, $header);
    }
    catch (\Exception) {
      return new Response('Invalid date format', 400);
    }
    $vcalendar = str_replace('kigkonsult.se ', '', $vcalendar);
    $responseHeader = [
      'Content-Type' => 'text/calendar; charset=utf-8',
      'Content-Disposition' => 'attachment; filename=event.ics',
    ];
    $response = new Response($vcalendar, 200, $responseHeader);
    return $response;
  }

  /**
   * Builds the response.
   *
   * {@inheritdoc}
   */
  public function feed(Request $request, $entity_type, $entity_id, $field_name, $view_mode = 'default') {
    $query = $request->query->all();
    $entityManager = $this->entityTypeManager();
    if (!$entityManager->hasDefinition($entity_type)) {
      throw new NotFoundHttpException();
    }
    $entity = $entityManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      throw new NotFoundHttpException();
    }
    // Check entity-level view access.
    $account = $this->currentUser();
    if (!$entity->access('view', $account)) {
      throw new AccessDeniedHttpException();
    }
    if (!$entity->hasField($field_name)) {
      throw new NotFoundHttpException();
    }
    // Check field-level access on the date field.
    if (!$entity->get($field_name)->access('view', $account)) {
      throw new AccessDeniedHttpException();
    }
    $header = [
      'RELCALID' => implode('-', [$entity_type, $entity_id, $field_name]),
      'TIMEZONE' => date_default_timezone_get(),
    ];
    $title = '';
    $ns = ['label', 'getTitle', 'getDisplayName', 'getName', 'getLabel'];
    if (is_object($entity)) {
      foreach ($ns as $name) {
        if (method_exists($entity, $name)) {
          $title = $entity->$name();
          break;
        }
      }
    }
    $header['CALNAME'] = $title;
    $properties = [
      'targetEntityType' => $entity_type,
      'bundle' => $entity->bundle(),
      'view_mode' => $view_mode,
    ];
    $viewDisplay = $entityManager->getStorage('entity_view_display');
    $view_display = $viewDisplay->load(implode('.', $properties));
    if (empty($view_display)) {
      $properties['view_mode'] = 'default';
      $view_display = $viewDisplay->load(implode('.', $properties));
    }
    $fieldSettings = $view_display->getComponent($field_name)['settings'];
    if (!empty($query)) {
      $fieldSettings = $query;
    }
    $fieldDefinitions = $this->entity_field_manager->getFieldDefinitions($entity_type, $entity->bundle());
    $listOption = [];
    foreach ($fieldDefinitions as $fieldName => $field) {
      if ($field instanceof FieldConfigInterface) {
        $fieldType = $field->getType();
        $listOption[$fieldType][] = $fieldName;
      }
    }
    $events = [];
    foreach ($entity->get($field_name)->getValue() as $date_field) {
      $iCal['date_field'] = $date_field;
      foreach ($fieldSettings as $key => $fieldMapping) {
        // Check field-level access on the mapped field.
        if (!$this->isAccessibleFieldMapping($entity, $fieldDefinitions, $fieldMapping, $account)) {
          continue;
        }
        if ($entity->hasField('created') && !empty($entity->get('created'))) {
          $date = DrupalDateTime::createFromTimestamp($entity->get('created')->value, 'UTC');
          $iCal['created'] = $date->format("Y-m-d\TH:i:s");
        }
        if ($entity->hasField('changed') && !empty($entity->get('changed'))) {
          $date = DrupalDateTime::createFromTimestamp($entity->get('changed')->value, 'UTC');
          $iCal['last-modified'] = $date->format("Y-m-d\TH:i:s");
        }
        $type = '';
        if (!empty($listOption["entity_reference"]) && in_array($fieldMapping, $listOption["entity_reference"]) && !empty($entity->get($fieldMapping))) {
          $entityRef = $entity->get($fieldMapping)->referencedEntities();
          if (!empty($entityRef)) {
            $entityRef = current($entityRef);
            $type = $entityRef->getEntityTypeId();
          }
          $ns = ['label', 'getTitle', 'getDisplayName', 'getName', 'getLabel'];
          if (is_object($entity)) {
            foreach ($ns as $name) {
              if (method_exists($entity, $name)) {
                $entityTitle = $entity->$name();
                break;
              }
            }
          }
          $iCal[$key] = $entityTitle ?? '';
        }
        switch ($key) {
          case 'date_field':
          case 'geo_field':
            $iCal[$key] = $entity->get($fieldMapping)->value;
            break;

          case 'location_field':
            $render_array = $entity->$fieldMapping->view('full');
            $render_array["#label_display"] = 'hidden';
            $location = (string) $this->renderer->renderInIsolation($render_array);
            // Render the result.
            $iCal[$key] = preg_replace('#\s+#', ' ', trim(strip_tags($location)));
            break;

          case 'organizer_field':
          case 'attendee_field':
            if ($type == 'user') {
              $iCal[$key] = [];
              foreach ($entity->get($fieldMapping)->referencedEntities() as $entityRef) {
                $iCal[$key][] = [
                  'name' => $entityRef->getDisplayName(),
                  'mail' => $entityRef->getEmail(),
                ];
              }
            }
            else {
              $invites = explode(', ', $entity->get($fieldMapping)
                ->getString());
              $iCal[$key] = [];
              foreach ($invites as $index => $invite) {
                preg_match_all('/([\w+\.]*\w+@[\w+\.]*\w+[\w+\-\w+]*\.\w+)/is', trim($invite), $matches);
                $mail = current($matches[0]);
                $iCal[$key][$index]['mail'] = $mail;
                $find = [$mail, '<', '>', '.'];
                $name = trim(ucwords(str_replace($find, ' ', $invite)));
                if (empty($name)) {
                  $ext = explode('@', $invite);
                  $name = trim(ucwords(str_replace($find, ' ', $ext[0])));
                }
                $iCal[$key][$index]['name'] = trim($name);
              }
            }
            break;

          case 'attach_field':
            $entityField = $entity->get($fieldMapping);
            $attaches = $entityField?->getValue();
            $fieldEntities = is_object($entityField) && method_exists($entityField, 'referencedEntities') ? $entityField->referencedEntities() : '';
            $attachments = [];
            foreach ($attaches as $index => $attach) {
              if (!empty($attach["target_id"])) {
                $fieldEntity = $fieldEntities[$index];
                if (count($attach) == 1) {
                  $attachments[] = $fieldEntity->toUrl()->setAbsolute()->toString();
                }
                else {
                  $attachments[] = $fieldEntity->createFileUrl(FALSE);
                }
              }
              if (!empty($attach["uri"])) {
                $attachments[] = Url::fromUri($attach["uri"])->setAbsolute()->toString();
              }
            }
            $iCal[$key] = $attachments;
            break;

          default:
            if ($entity->get($fieldMapping) && !empty($entity->get($fieldMapping)?->value)) {
              $iCal[$key] = $entity->get($fieldMapping)->value;
            }
            break;
        }
      }
      $events[] = $iCal;
    }
    $vcalendar = $this->dateIcalFeed->feed($events, $header);
    $vcalendar = str_replace('kigkonsult.se ', '', $vcalendar);
    $responseHeader = [];
    if (!empty($fieldSettings['download_directly'])) {
      $responseHeader = ['Content-Type' => 'text/calendar; charset=utf-8'];
    }
    $response = new Response($vcalendar, 200, $responseHeader);
    return $response;
  }

  /**
   * Checks whether a requested field mapping is accessible to the given user.
   */
  private function isAccessibleFieldMapping($entity, array $fieldDefinitions, mixed $fieldMapping, AccountInterface $account): bool {
    if (!is_string($fieldMapping) || !isset($fieldDefinitions[$fieldMapping]) || !$entity->hasField($fieldMapping)) {
      return FALSE;
    }
    return $entity->get($fieldMapping)->access('view', $account);
  }

}
