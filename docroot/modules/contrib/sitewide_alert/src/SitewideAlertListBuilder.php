<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;

/**
 * Defines a class to build a listing of Sitewide Alert entities.
 *
 * @ingroup sitewide_alert
 */
class SitewideAlertListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['name'] = $this->t('Name');
    $header['style'] = $this->t('Style');
    $header['active'] = $this->t('Active');
    $header['scheduled'] = $this->t('Scheduled');
    $header['visibility'] = $this->t('Page visibility');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\sitewide_alert\Entity\SitewideAlert $entity */
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.sitewide_alert.canonical',
      ['sitewide_alert' => $entity->id()]
    );
    $row['style'] = AlertStyleProvider::alertStyleName($entity->getStyle());
    $row['active'] = $entity->isPublished() ? '✔' : '✘';
    $row['scheduled'] = $entity->isScheduled() ? '✔' : '✘';
    $row['visibility'] = $this->buildVisibilityCell($entity);
    return $row + parent::buildRow($entity);
  }

  /**
   * Builds the Page visibility table cell.
   */
  protected function buildVisibilityCell(EntityInterface $entity): array {
    // Get pages.
    $pages = $entity->getPagesToShowOn();
    if (!is_array($pages)) {
      $pages = preg_split('/\R/', (string) $pages, -1, PREG_SPLIT_NO_EMPTY);
    }
    $pages = array_filter(array_map('trim', $pages));

    // No restrictions All pages.
    if (!$pages) {
      return ['data' => ['#plain_text' => (string) $this->t('All pages')]];
    }

    // Negate (hide on these pages) add "Except:" prefix to each line.
    $negate = $entity->hasField('limit_to_pages_negate')
      ? (bool) ($entity->get('limit_to_pages_negate')->value ?? FALSE)
      : FALSE;

    if ($negate) {
      $pages = array_map(function ($path) {
        return '<strong>Except:</strong> ' . Html::escape(trim($path));
      }, $pages);
    }
    else {
      $pages = array_map([Html::class, 'escape'], $pages);
    }

    // List pages.
    return [
      'data'  => ['#markup' => Markup::create(implode('<br>', $pages))],
      'class' => ['sitewide-alert-visibility'],
    ];
  }

}
