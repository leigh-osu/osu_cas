<?php

declare(strict_types=1);

namespace Drupal\Tests\node_revision_delete\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests prior revision deletion via the UI.
 */
#[Group('node_revision_delete')]
#[RunTestsInSeparateProcesses]
class PriorRevisionDeleteViaUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'language',
    'node',
    'node_revision_delete',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->config('node_revision_delete.settings')->set('bulk_delete_threshold', 2)->save();
    // Create a content type with revisions.
    $this->drupalCreateContentType(['type' => 'page', 'revision' => TRUE]);

    // Create an admin user who can manage languages and delete revisions.
    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer languages',
      'administer nodes',
      'delete all revisions',
      'view all revisions',
      'edit any page content',
    ]);
    $this->drupalLogin($admin);
  }

  /**
   * Tests prior revisions are shown when admin language differs from content.
   */
  public function testPriorRevisionsShownWithAdminLanguage(): void {
    // Add a second language.
    ConfigurableLanguage::createFromLangcode('nl')->save();

    // Enable "Account administration pages" language detection for the
    // interface language, positioned above the URL method.
    $this->drupalGet('admin/config/regional/language/detection');
    $this->submitForm([
      'language_interface[enabled][language-user-admin]' => TRUE,
      'language_interface[enabled][language-url]' => TRUE,
      'language_interface[weight][language-user-admin]' => -12,
      'language_interface[weight][language-url]' => -10,
    ], 'Save settings');

    // Enable "Use admin theme for content editing" so that node operation
    // routes (including revision delete) are marked as admin routes.
    $this->config('node.settings')->set('use_admin_theme', TRUE)->save();
    // Rebuild the router so the _admin_route option takes effect.
    $this->rebuildAll();

    // Set the user's preferred admin language to Dutch.
    $this->drupalGet('/user/edit');
    $this->submitForm([
      'preferred_admin_langcode' => 'nl',
    ], 'Save');

    // Create an English node with 5 revisions.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'English node',
      'langcode' => 'en',
    ]);
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    for ($i = 2; $i <= 5; $i++) {
      $node->setTitle('English node v' . $i);
      $node = $node_storage->createRevision($node);
      $node->save();
    }

    // We have VIDs 1-5. Delete a non-active revision (the 4th) which has 3
    // prior revisions (VIDs 1-3).
    $all_vids = array_keys(
      $node_storage->getQuery()
        ->allRevisions()
        ->condition('nid', $node->id())
        ->sort('vid', 'ASC')
        ->accessCheck(FALSE)
        ->execute()
    );
    // Target the second-to-last revision.
    $target_vid = $all_vids[3];

    // Visit the revision delete confirmation page.
    $this->drupalGet("node/{$node->id()}/revisions/{$target_vid}/delete");
    $this->assertSession()->statusCodeEquals(200);

    // The "Delete prior revisions" details box must be visible.
    $this->assertSession()->pageTextContains('Delete prior revisions');
    $this->assertSession()->fieldExists('delete_prior_revisions');
  }

  /**
   * Tests the prior revisions table is limited to 20 rows.
   *
   * When there are more than 20 prior revisions, only the 20 most recent are
   * displayed in the table, with a final row indicating how many additional
   * revisions will also be deleted.
   */
  public function testPriorRevisionsTableTruncation(): void {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    // Create a node with 25 revisions (1 original + 24 new).
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test node v1',
      'langcode' => 'en',
    ]);
    for ($i = 2; $i <= 25; $i++) {
      $node->setTitle('Test node v' . $i);
      $node = $node_storage->createRevision($node);
      $node->save();
    }

    $all_vids = array_map('intval', array_keys(
      $node_storage->getQuery()
        ->allRevisions()
        ->condition('nid', $node->id())
        ->sort('vid', 'ASC')
        ->accessCheck(FALSE)
        ->execute()
    ));
    $this->assertCount(25, $all_vids);

    // Target the second-to-last revision for deletion. This gives us 23 prior
    // revisions, which exceeds the display limit of 20.
    $target_vid = $all_vids[count($all_vids) - 2];

    $this->drupalGet("node/{$node->id()}/revisions/{$target_vid}/delete");
    $this->assertSession()->statusCodeEquals(200);

    // The checkbox should mention all 23 prior revisions.
    $this->assertSession()->pageTextContains('Also delete 23 revisions prior to this one.');

    // The table should show only 20 revision rows plus the "more" row.
    $table_rows = $this->getSession()->getPage()->findAll('css', 'table tbody tr');
    // 20 revision rows + 1 "And X more" row = 21.
    $this->assertCount(21, $table_rows);

    // The last row should indicate the 3 remaining revisions.
    $this->assertSession()->pageTextContains('And 3 more revisions that will also be deleted.');

    // Submit the form and verify all 23 prior revisions are deleted.
    $this->submitForm(['delete_prior_revisions' => 1], 'Delete');
    $this->assertSession()->pageTextContains('23 prior revisions');

    $node_storage->resetCache();
    $remaining_count = (int) $node_storage->getQuery()
      ->allRevisions()
      ->condition('nid', $node->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    // 25 total - 1 deleted via the form itself - 23 prior = 1 (the active
    // revision).
    $this->assertSame(1, $remaining_count);
  }

  /**
   * Tests that all prior revisions are shown when there are 20 or fewer.
   */
  public function testPriorRevisionsTableNoTruncation(): void {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    // Create a node with 5 revisions.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test node v1',
      'langcode' => 'en',
    ]);
    for ($i = 2; $i <= 5; $i++) {
      $node->setTitle('Test node v' . $i);
      $node = $node_storage->createRevision($node);
      $node->save();
    }

    $all_vids = array_map('intval', array_keys(
      $node_storage->getQuery()
        ->allRevisions()
        ->condition('nid', $node->id())
        ->sort('vid', 'ASC')
        ->accessCheck(FALSE)
        ->execute()
    ));
    // Target the second-to-last revision. This gives 3 prior revisions.
    $target_vid = $all_vids[count($all_vids) - 2];

    $this->drupalGet("node/{$node->id()}/revisions/{$target_vid}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Also delete 3 revisions prior to this one.');

    // All 3 revisions should be in the table with no truncation row.
    $table_rows = $this->getSession()->getPage()->findAll('css', 'table tbody tr');
    $this->assertCount(3, $table_rows);
    $this->assertSession()->pageTextNotContains('more revisions that will also be deleted.');
    $this->assertSession()->pageTextNotContains('more revision that will also be deleted.');
  }

  /**
   * Tests that prior revision deletion preserves active VIDs for other languages.
   *
   * When an untranslatable field is changed while updating a translation, the
   * resulting revision has revision_translation_affected = 1 for both
   * languages. This revision becomes the active VID for the translation's
   * language. The "delete prior revisions" feature must not delete that revision
   * when deleting prior English revisions, even though it is older than the
   * targeted English revision.
   */
  public function testPriorRevisionDeletionPreservesActiveVidForOtherLanguage(): void {
    // Add a second language.
    ConfigurableLanguage::createFromLangcode('de')->save();

    // Add a non-translatable field to the page content type.
    FieldStorageConfig::create([
      'field_name' => 'field_untranslatable',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_untranslatable',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Untranslatable field',
      'translatable' => FALSE,
    ])->save();

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    // Step 1: Create an English node.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'English node',
      'langcode' => 'en',
      'field_untranslatable' => 'initial value',
    ]);

    // Step 2: Add a German translation while also changing the untranslatable
    // field. This creates a revision where revision_translation_affected = 1
    // for both EN and DE. This revision becomes the active VID for DE.
    $translation = $node->addTranslation('de', ['title' => 'German title']);
    $translation->set('field_untranslatable', 'changed value');
    $new_revision = $node_storage->createRevision($translation);
    $new_revision->save();
    $de_active_vid = (int) $new_revision->getRevisionId();

    // Step 3: Create several more English-only revisions so the revision from
    // step 2 becomes a "prior revision" relative to a later English revision.
    for ($i = 0; $i < 4; $i++) {
      $node = $node_storage->load($node->id());
      $node->setTitle('English node v' . ($i + 3));
      $new_revision = $node_storage->createRevision($node);
      $new_revision->save();
    }

    // Collect all VIDs sorted ascending.
    $all_vids = array_map('intval', array_keys(
      $node_storage->getQuery()
        ->allRevisions()
        ->condition('nid', $node->id())
        ->sort('vid', 'ASC')
        ->accessCheck(FALSE)
        ->execute()
    ));

    $this->assertCount(6, $all_vids, 'There should be 6 revisions.');

    // The DE active VID should be one of the prior revisions from English's
    // perspective.
    $this->assertContains($de_active_vid, $all_vids);

    // Target the last non-active revision for deletion (second to last VID).
    $target_vid = $all_vids[count($all_vids) - 2];
    $this->assertGreaterThan($de_active_vid, $target_vid, 'Target VID must be after the DE active VID so that DE active VID is a prior revision.');

    // Visit the revision delete confirmation page and submit with "delete
    // prior revisions" checked.
    $this->drupalGet("node/{$node->id()}/revisions/{$target_vid}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('delete_prior_revisions');
    $this->submitForm(['delete_prior_revisions' => 1], 'Delete');
    $this->assertSession()->pageTextContains('3 prior revisions have been deleted.');
    $this->assertSession()->pageTextContains('Revision 2 [nid: 1] cannot be deleted as it is the active revision for de language.');

    // The DE active VID must still exist — it must not have been deleted.
    $node_storage->resetCache();
    $de_revision = $node_storage->loadRevision($de_active_vid);
    $this->assertInstanceOf(NodeInterface::class, $de_revision);
    $this->assertNotNull($de_revision, 'The revision that is the active VID for German must not be deleted by prior revision deletion.');
    $this->assertTrue($de_revision->hasTranslation('de'), 'The German translation must still exist on the preserved revision.');
    $revision_count = $node_storage->getQuery()
      ->allRevisions()
      ->condition('nid', $node->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertSame(2, $revision_count, 'There should be 3 revisions.');
  }

}
