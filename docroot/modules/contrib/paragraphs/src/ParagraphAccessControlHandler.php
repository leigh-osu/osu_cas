<?php

namespace Drupal\paragraphs;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\paragraphs_library\LibraryItemInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the paragraphs entity.
 *
 * @see \Drupal\paragraphs\Entity\Paragraph.
 */
class ParagraphAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Contains the configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Per-request memoization for the parent-revision lookup.
   *
   * Keyed by "{parent_type}:{parent_id}:{field_name}:{child_revision_id}";
   * values are the resolved parent revision id (int) or 0 for "no match".
   *
   * @var array<string, int>
   */
  protected $hostRevisionCache = [];

  /**
   * Constructs a ParagraphAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config object factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entity_type_manager
   *   The entity type manager. Optional for BC; falls back to the container.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, ?EntityTypeManagerInterface $entity_type_manager = NULL) {
    parent::__construct($entity_type);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager ?? \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $paragraph, $operation, AccountInterface $account) {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $config = $this->configFactory->get('paragraphs.settings');
    if ($operation === 'view') {
      $access_result = AccessResult::allowedIf($paragraph->isPublished() || ($account->hasPermission('view unpublished paragraphs') && $config->get('show_unpublished')))->addCacheableDependency($config);
    }
    else {
      $access_result = AccessResult::allowed();
    }

    if ($paragraph->getParentEntity() == NULL) {
      return $access_result;
    }

    // Delete permission on the paragraph, should just depend on 'update'
    // access permissions on the parent.
    $operation = ($operation == 'delete') ? 'update' : $operation;
    $parent = $paragraph->getParentEntity();
    if (!$parent instanceof LibraryItemInterface || $operation !== 'view') {
      $parent_access = $parent->access($operation, $account, TRUE);
      $access_result = $access_result->andIf($parent_access);
    }
    elseif (!$parent->isPublished()) {
      // Replicate the \Drupal\paragraphs_library\LibraryItemAccessControlHandler::checkAccess() access check.
      $access_result = $access_result->andIf(AccessResult::allowedIfHasPermission($account, $parent->getEntityType()->getAdminPermission()));
    }

    $parent_type_id = $parent->getEntityTypeId();
    if ($parent_type_id == 'paragraphs_library_item') {
      return $access_result;
    }
    // Paragraphs on blocks and paragraphs can get into a state that makes it
    // difficult to find the correct revision of the parent to check access
    // against, so return the current access result in that case.
    $skip_types = ['block_content', 'paragraph'];
    if (($operation == 'view' || $operation == 'update') && in_array($parent_type_id, $skip_types)) {
      return $access_result;
    }
    // Now check access on the paragraph's parent.
    $parent_access = $parent->access($operation, $account, TRUE);
    $access_result = $access_result->andIf($parent_access);

    // Walk up nested paragraph parents to the top-level host entity, and
    // -- where possible -- load the specific revision that references this
    // paragraph chain. This addresses #3090200: under content moderation
    // or after a revert, the default-revision parent can have different
    // access than the revision the paragraph actually belongs to.
    $host = $this->resolveAccessHost($paragraph);
    // Library items don't participate in parent-based access checking.
    if ($host !== NULL && $host->getEntityTypeId() !== 'paragraphs_library_item') {
      $parent_access = $host->access($operation, $account, TRUE);
      $access_result = $access_result->andIf($parent_access);
    }

    return $access_result;
  }

  /**
   * Resolves the top-level host entity used for parent access checks.
   *
   * Walks the parent chain past any intermediate paragraph parents to the
   * first non-paragraph entity. At each hop, tries to load the specific
   * revision of the parent that references the current entity, so the
   * resolved host reflects content-moderation drafts and reverted revisions
   * rather than always the default revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $paragraph
   *   The paragraph whose access is being checked.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The host entity (possibly a non-default revision), or NULL if there is
   *   no resolvable host.
   */
  protected function resolveAccessHost(EntityInterface $paragraph) {
    $current = $paragraph;
    // Guard against pathological cycles in malformed data.
    $depth = 0;
    while ($current instanceof ParagraphInterface && $depth++ < 50) {
      $parent = $current->getParentEntity();
      if ($parent === NULL) {
        return NULL;
      }
      // For revisionable parents, swap to the revision that actually
      // references $current — otherwise the default revision lookup from
      // getParentEntity() can yield the wrong access decision (#3090200).
      // Resolve at every hop so nested-paragraph chains aren't anchored to
      // a default outer-paragraph revision either.
      $parent = $this->loadReferencingRevision($parent, $current) ?? $parent;
      if ($parent->getEntityTypeId() === 'paragraph') {
        $current = $parent;
        continue;
      }
      return $parent;
    }
    return NULL;
  }

  /**
   * Loads the parent revision that references the given child paragraph.
   *
   * @param \Drupal\Core\Entity\EntityInterface $parent
   *   The parent entity (typically the default revision from getParentEntity).
   * @param \Drupal\paragraphs\ParagraphInterface $child
   *   The paragraph attached to $parent via its parent field.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The most recent parent revision whose entity_reference_revisions field
   *   targets the child's revision id, or NULL if none was found (or the
   *   parent isn't revisionable).
   */
  protected function loadReferencingRevision(EntityInterface $parent, ParagraphInterface $child) {
    $parent_type = $parent->getEntityType();
    if (!$parent_type->isRevisionable()) {
      return NULL;
    }
    $field_name = $child->get('parent_field_name')->value;
    $child_revision_id = $child->getRevisionId();
    if (!$field_name || !$child_revision_id) {
      return NULL;
    }

    // Fast path: the parent we already have in hand may already be the
    // revision that references this child. Saves the entity query in the
    // common case where the default revision *is* the referencing revision.
    if ($parent->hasField($field_name)
        && $this->parentReferencesChildRevision($parent, $field_name, $child_revision_id)) {
      return $this->applyTranslation($parent, $child);
    }

    $storage = $this->entityTypeManager->getStorage($parent->getEntityTypeId());
    $cache_key = $parent->getEntityTypeId() . ':' . $parent->id() . ':' . $field_name . ':' . $child_revision_id;
    if (array_key_exists($cache_key, $this->hostRevisionCache)) {
      $revision_id = $this->hostRevisionCache[$cache_key];
      if (!$revision_id) {
        return NULL;
      }
      $loaded = $storage->loadRevision($revision_id);
      return $loaded ? $this->applyTranslation($loaded, $child) : NULL;
    }

    $id_key = $parent_type->getKey('id');
    $revision_key = $parent_type->getKey('revision');
    $revision_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($id_key, $parent->id())
      ->condition($field_name . '.target_revision_id', $child_revision_id)
      ->sort($revision_key, 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$revision_ids) {
      $this->hostRevisionCache[$cache_key] = 0;
      return NULL;
    }
    $revision_id = (int) key($revision_ids);
    $this->hostRevisionCache[$cache_key] = $revision_id;
    $loaded = $storage->loadRevision($revision_id);
    return $loaded ? $this->applyTranslation($loaded, $child) : NULL;
  }

  /**
   * Checks whether $parent's ERR field references $child_revision_id.
   *
   * @param \Drupal\Core\Entity\EntityInterface $parent
   *   The already-loaded parent entity.
   * @param string $field_name
   *   The entity_reference_revisions field on the parent that references
   *   paragraphs.
   * @param int $child_revision_id
   *   The paragraph revision id to look for.
   *
   * @return bool
   */
  protected function parentReferencesChildRevision(EntityInterface $parent, $field_name, $child_revision_id) {
    foreach ($parent->get($field_name) as $item) {
      if ((int) $item->target_revision_id === (int) $child_revision_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the child-language translation of the parent, if available.
   *
   * Mirrors Paragraph::getParentEntity()'s translation handling so the
   * revision-aware lookup doesn't strip language context.
   */
  protected function applyTranslation(EntityInterface $entity, ParagraphInterface $child) {
    $langcode = $child->language()->getId();
    if ($entity instanceof TranslatableInterface && $entity->hasTranslation($langcode)) {
      return $entity->getTranslation($langcode);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Allow paragraph entities to be created in the context of entity forms.
    if (\Drupal::requestStack()->getCurrentRequest()->getRequestFormat() === 'html') {
      return AccessResult::allowed()->addCacheContexts(['request_format']);
    }
    return AccessResult::neutral()->addCacheContexts(['request_format']);
  }

}
