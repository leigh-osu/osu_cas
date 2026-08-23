<?php

namespace Drupal\node_revision_delete\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node_revision_delete\NodeRevisionDeleteBatch;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Drush\Commands\DrushCommands;

/**
 * The Prior Revision Delete Commands.
 *
 * @package Drupal\node_revision_delete\Commands
 */
class PriorRevisions extends DrushCommands {

  public function __construct(
    protected NodeRevisionDeleteInterface $nodeRevisionDelete,
    protected NodeRevisionDeleteBatch $nodeRevisionDeleteBatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Deletes all revisions prior to a revision.
   *
   * @param int $nid
   *   The id of the node which revisions will be deleted.
   * @param int $vid
   *   The revision id, all prior revisions to this revision will be deleted.
   * @param array $options
   *   An associative array of options.
   *
   * @option langcode The language code to filter revisions by. When not
   *   specified, the site default language is used.
   *
   * @usage nrd-delete-prior-revisions 1 3
   *   Delete all revisions prior to revision id 3 of node id 1.
   * @usage nrd-delete-prior-revisions 1 3 --langcode=nl
   *   Delete all Dutch revisions prior to revision id 3 of node id 1.
   *
   * @command node-revision-delete:delete-prior-revisions
   *
   * @aliases nrd:delete-prior-revisions,nrd-dpr,nrd-delete-prior-revisions
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function deletePriorRevisions(int $nid = 0, int $vid = 0, array $options = ['langcode' => NULL]): void {
    if ($this->nodeRevisionDeleteBatch->previousRevisionDeletionBatch($nid, $vid, $options['langcode'] ?? NULL)) {
      drush_backend_batch_process();
    }
    else {
      $this->io()->error(dt('No prior revision(s) found to delete.'));
    }
  }

  /**
   * Validate inputs before executing the drush command nrd-dpr.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @return bool
   *   Returns TRUE if the validations has passed FALSE otherwise.
   *
   * @hook validate nrd-delete-prior-revisions
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function deletePriorRevisionsValidate(CommandData $commandData): bool {
    $input = $commandData->input();

    if ($langcode = $input->getOption('langcode')) {
      if (!$this->languageManager->getLanguage($langcode)) {
        $this->io()->error(dt('The language code @langcode is not a valid language code.', ['@langcode' => $langcode]));
        return FALSE;
      }

    }
    else {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      $input->setOption('langcode', $langcode);
    }

    $nid = $input->getArgument('nid');
    $vid = $input->getArgument('vid');

    // Check if argument nid is a valid node id.
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->load($nid);
    if (is_null($node)) {
      $this->io()->error(dt('@nid is not a valid node id.', ['@nid' => $nid]));
      return FALSE;
    }

    // Get all revisions of the current node is the language we're using.
    $query = $node_storage
      ->getQuery()
      ->allRevisions()
      ->condition('nid', $nid)
      ->condition('vid', $vid)
      ->condition('langcode', $langcode)
      ->condition('revision_translation_affected', 1)
      ->accessCheck(FALSE)
      ->count();

    if ($query->execute() === 0) {
      $this->io()->error(dt('@vid is not a valid revision id for node @nid with the langcode @langcode.', [
        '@vid' => $vid,
        '@nid' => $nid,
        '@langcode' => $langcode,
      ]));
      return FALSE;
    }

    return TRUE;
  }

}
