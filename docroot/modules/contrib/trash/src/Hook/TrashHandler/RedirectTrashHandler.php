<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\path_alias\PathAliasInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;
use Drupal\trash\Exception\UnrestorableEntityException;
use Drupal\trash\Handler\DefaultTrashHandler;

/**
 * Provides a trash handler for the 'redirect' entity type.
 */
class RedirectTrashHandler extends DefaultTrashHandler {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ?RedirectRepository $redirectRepository = NULL,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_presave() for the 'redirect' entity type.
   */
  #[Hook('redirect_presave')]
  public function preSave(EntityInterface $entity): void {
    assert($entity instanceof Redirect);
    if (!$this->trashManager->isEntityTypeEnabled($entity->getEntityTypeId())) {
      return;
    }

    // Set a random hash value for deleted redirects in order to allow new ones
    // to be created with the same source URL.
    if (!$entity->get('deleted')->isEmpty()) {
      $entity->set('hash', 'deleted-' . $entity->uuid());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for path_alias.
   *
   * Replaces the Redirect module's implementation so that, when a matching
   * redirect was previously trashed, it can be restored instead of a new one
   * being created. A fresh redirect entity's default revision is always live
   * even inside a workspace, while trashing an existing one only updates a
   * pending revision there, so creating a new redirect for a source that was
   * trashed earlier in the same workspace would collide with the untouched live
   * revision's hash. Restoring the trashed entity reuses that row instead of
   * competing with it.
   *
   * The Redirect module implements this hook procedurally on its latest
   * stable release, and as an OOP hook method on newer, unreleased versions
   * that convert hooks to attributes. Both targets are removed below;
   * whichever one doesn't exist in the installed version is a no-op, since
   * class-name references in attribute arguments don't trigger autoloading.
   *
   * This hook is not gated on the 'redirect' module being installed (unlike
   * 'redirect_presave' above, which can't fire without it), so it must check
   * for that itself; the fallback below calls a Redirect module function that
   * wouldn't exist otherwise.
   */
  #[Hook('path_alias_update')]
  #[RemoveHook('path_alias_update', class: ProceduralCall::class, method: 'redirect_path_alias_update')]
  #[RemoveHook('path_alias_update', class: 'Drupal\redirect\Hook\RedirectEntityHooks', method: 'pathAliasUpdate')]
  public function pathAliasUpdate(PathAliasInterface $path_alias): void {
    if (!$this->redirectRepository) {
      return;
    }

    if (!$this->tryRestoreTrashedRedirect($path_alias, $this->redirectRepository)) {
      // Fall back to the Redirect module's own implementation whenever there is
      // nothing to restore.
      redirect_path_alias_update($path_alias);
    }
  }

  /**
   * Tries to restore a trashed redirect instead of creating a new one.
   *
   * @param \Drupal\path_alias\PathAliasInterface $path_alias
   *   The path alias that was just updated.
   * @param \Drupal\redirect\RedirectRepository $redirect_repository
   *   The redirect repository.
   *
   * @return bool
   *   TRUE if a trashed redirect was restored and no further action is
   *   needed; FALSE if the caller should fall back to the Redirect module's
   *   own implementation.
   */
  protected function tryRestoreTrashedRedirect(PathAliasInterface $path_alias, RedirectRepository $redirect_repository): bool {
    if (!$this->trashManager->isEntityTypeEnabled('redirect')) {
      return FALSE;
    }

    $config = $this->configFactory->get('redirect.settings');
    if (!$config->get('auto_redirect')) {
      return FALSE;
    }

    /** @var \Drupal\path_alias\PathAliasInterface $original_path_alias */
    // @phpstan-ignore-next-line
    $original_path_alias = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $path_alias->getOriginal(), fn() => $path_alias->original);
    if ($original_path_alias->getAlias() === $path_alias->getAlias()) {
      return FALSE;
    }

    // Language is part of the redirect hash, so a fresh redirect for the old
    // alias would use the original alias's language. Matching the trashed
    // redirect on that same language targets the exact row a new one would
    // collide with, so a changed language needs no early return of its own.
    $langcode = $original_path_alias->language()->getId();
    if ($redirect_repository->findMatchingRedirect($original_path_alias->getAlias(), [], $langcode)) {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage('redirect');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source.path', ltrim($original_path_alias->getAlias(), '/'), '=')
      ->condition('language', $langcode)
      ->exists('deleted')
      ->sort('rid')
      ->execute();
    if (!$ids) {
      return FALSE;
    }

    $trashed = $this->trashManager->executeInTrashContext('ignore', fn () => $storage->loadMultiple($ids));

    // The auto-redirect feature only creates redirects with an empty source
    // query, so skip any trashed candidate that carries query parameters. The
    // 'query' property is serialized, so it can't be filtered in the entity
    // query above. Sorting by 'rid' keeps the pick stable when more than one
    // candidate matches.
    $redirect = NULL;
    foreach ($trashed as $candidate) {
      assert($candidate instanceof Redirect);
      if (empty($candidate->get('redirect_source')->query)) {
        $redirect = $candidate;
        break;
      }
    }
    if (!$redirect) {
      return FALSE;
    }

    $redirect->setRedirect($path_alias->getPath());
    try {
      $storage->restoreFromTrash([$redirect]);
    }
    catch (UnrestorableEntityException) {
      // A live redirect with the same hash already exists, so there is nothing
      // to restore. Let the Redirect module create a fresh redirect instead of
      // letting the exception abort the whole path alias save.
      return FALSE;
    }

    // Still perform the same source-path cleanup the Redirect module's own
    // implementation does for the new alias.
    redirect_delete_by_path($path_alias->getAlias(), $path_alias->language()->getId(), FALSE);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateRestore(EntityInterface $entity): void {
    assert($entity instanceof Redirect);
    $storage = $this->entityTypeManager->getStorage('redirect');

    // Execute the redirect's preSave() method directly to restore the original
    // hash value.
    $entity->preSave($storage);

    // Check if there's a non-deleted redirect with the same hash (source URL).
    $result = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('hash', $entity->get('hash')->value, '=')
      ->notExists('deleted')
      ->range(0, 1)
      ->execute();

    if ($result) {
      throw new UnrestorableEntityException((string) $this->t('There is an existing redirect with the same source URL.'));
    }

    $this->markRestoreValidated($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function preTrashRestore(EntityInterface $entity): void {
    $this->ensureRestoreValidated($entity);
  }

}
