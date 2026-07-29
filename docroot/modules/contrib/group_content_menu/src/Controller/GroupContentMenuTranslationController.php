<?php

declare(strict_types=1);

namespace Drupal\group_content_menu\Controller;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\content_translation\Controller\ContentTranslationController;
use Drupal\group\Entity\GroupInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Override the default content translation controller to add group permissions.
 */
class GroupContentMenuTranslationController extends ContentTranslationController {

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    $build = parent::overview($route_match, $entity_type_id);

    if (!$this->languageManager()->isMultilingual()) {
      return $build;
    }

    $group_content_menu = $route_match->getParameter('group_content_menu');
    $entity = $route_match->getParameter('menu_link_content');
    $languages = $this->languageManager()->getLanguages();
    $group = $route_match->getParameter('group');
    $rows =& $build['content_translation_overview']['#rows'];
    $operations = [
      'edit' => $this->t('Edit'),
      'delete' => $this->t('Delete'),
    ];

    foreach (array_values($languages) as $delta => $language) {
      assert($language instanceof LanguageInterface);
      $translations = $this->getTranslations($entity, $entity_type_id, $language->getId());
      $row = &$rows[$delta];
      $last = array_key_last($row);
      if (array_key_exists($language->getId(), $translations)) {
        foreach ($operations as $operation => $label) {
          $this->buildRow($row, $last, $operation, $label, $group_content_menu, $group, $entity, $language);
        }
      }
      else {
        $this->buildRow($row, $last, 'add', $this->t('Add'), $group_content_menu, $group, $entity, $language);
      }
    }

    return $build;
  }

  private function getTranslations(MenuLinkContentInterface $entity, string $entity_type_id, string $langcode): array {
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $default_revision = $storage->load($entity->id());
    $entity = $default_revision;
    $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $langcode);
    if ($latest_revision_id) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $latest_revision */
      $latest_revision = $storage->loadRevision($latest_revision_id);
      // Make sure we do not list removed translations, i.e. translations
      // that have been part of a default revision but no longer are.
      if (!$latest_revision->wasDefaultRevision() || $default_revision->hasTranslation($langcode)) {
        $entity = $latest_revision;
      }
    }
    return $entity->getTranslationLanguages();
  }

  private function buildRow(array &$row, int $last, string $operation, TranslatableMarkup $label, GroupContentMenuInterface $groupContentMenu, GroupInterface $group, MenuLinkContentInterface $entity, LanguageInterface $language): void {
    $options = [
      'language' => $language,
      'query' => ['destination' => Url::fromRoute('<current>')->toString()],
    ];
    $row[$last]['data']['#links'][$operation] = [
      'title' => $label,
      'url' => $groupContentMenu->toUrl("translate-menu-$operation-link", $options)
        ->setRouteParameter('group', $group->id())
        ->setRouteParameter('group_content_menu', $groupContentMenu->id())
        ->setRouteParameter('menu_link_content', $entity->id())
        ->setRouteParameter('source', $entity->getUntranslated()->language()->getId())
        ->setRouteParameter('target', $language->getId())
        ->setRouteParameter('language', $language->getId()),
    ];
  }

}
