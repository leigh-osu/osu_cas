<?php

namespace Drupal\Tests\paragraphs\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\paragraphs\ParagraphAccessControlHandler
 * @group paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('paragraphs')]
class ParagraphsAccessTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paragraphs',
    'user',
    'system',
    'field',
    'file',
    'node',
    'entity_reference_revisions',
    'block_content',
    'content_moderation',
    'workflows',
    'filter',
  ];

  /**
   * @covers ::checkCreateAccess
   *
   * @dataProvider createAccessTestCases
   */
  #[DataProvider('createAccessTestCases')]
  public function testCreateAccess($request_format, AccessResult $expected_result) {

    $cache_contexts_manager = $this->prophesize(CacheContextsManager::class);
    $cache_contexts_manager->assertValidTokens()->willReturn(TRUE);
    $cache_contexts_manager->reveal();
    $this->container->set('cache_contexts_manager', $cache_contexts_manager);

    $expected_result->addCacheContexts(['request_format']);

    $request = new Request();
    $request->setRequestFormat($request_format);
    $this->container->get('request_stack')->push($request);
    $result = $this->container->get('entity_type.manager')->getAccessControlHandler('paragraph')->createAccess(NULL, NULL, [], TRUE);
    $this->assertEquals($expected_result, $result);
    $this->container->get('request_stack')->pop();
  }

  /**
   * Test cases for ::testCreateAccess.
   */
  public static function createAccessTestCases() {
    return [
      'Allowed HTML request format' => [
        'html',
        AccessResult::allowed(),
      ],
      'Forbidden other formats' => [
        'json',
        AccessResult::neutral(),
      ],
    ];
  }

  /**
   * Tests that a node parent's access decision gates the paragraph.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessParentEntityNode(): void {
    $this->setUpAccessFixtures();
    $user = $this->createUser();

    $paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'top-level',
    ]);
    $paragraph->save();
    $node = Node::create([
      'title' => 'Host',
      'type' => 'paragraphed_test',
      'field_paragraphs' => [$paragraph],
    ]);
    $node->save();

    $paragraph = $this->reload($paragraph);

    // The user has no 'access content', so node-level access is denied and
    // that must propagate to the paragraph for view, update, and delete.
    $this->assertFalse($paragraph->access('view', $user));
    $this->assertFalse($paragraph->access('update', $user));
    $this->assertFalse($paragraph->access('delete', $user));
  }

  /**
   * Tests that a paragraph parent walks up to the host for access checks.
   *
   * Issue #3090200: when a paragraph is nested inside another paragraph, the
   * access check must still consult the top-level host entity. Otherwise,
   * private content (e.g. files) in inner paragraphs could be exposed to
   * users who cannot access the host.
   *
   * @covers ::checkAccess
   * @covers ::resolveAccessHost
   */
  public function testCheckAccessParentEntityParagraph(): void {
    $this->setUpAccessFixtures();
    $user = $this->createUser();

    $inner = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'inner',
    ]);
    $inner->save();
    $outer = Paragraph::create([
      'type' => 'nested_paragraph',
      'field_nested' => [$inner],
    ]);
    $outer->save();
    $node = Node::create([
      'title' => 'Host',
      'type' => 'paragraphed_test',
      'field_paragraphs' => [$outer],
    ]);
    $node->save();

    $inner = $this->reload($inner);
    $outer = $this->reload($outer);

    // Outer paragraph's parent is the (forbidden) node.
    $this->assertFalse($outer->access('view', $user));
    $this->assertFalse($outer->access('update', $user));
    $this->assertFalse($outer->access('delete', $user));

    // Inner paragraph's parent chain walks up to the same forbidden node,
    // so it must also be denied. Anything else is a security regression --
    // inner paragraphs would otherwise expose host-restricted content.
    $this->assertFalse($inner->access('view', $user));
    $this->assertFalse($inner->access('update', $user));
    $this->assertFalse($inner->access('delete', $user));
  }

  /**
   * Tests that delete is gated by the host's update access, not bypassed.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessDeleteIsGatedByParentUpdate(): void {
    $this->setUpAccessFixtures();
    // A user with view-only access on the host.
    $viewer = $this->createUser(['access content']);

    $inner = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'inner',
    ]);
    $inner->save();
    $outer = Paragraph::create([
      'type' => 'nested_paragraph',
      'field_nested' => [$inner],
    ]);
    $outer->save();
    $node = Node::create([
      'title' => 'Host',
      'type' => 'paragraphed_test',
      'field_paragraphs' => [$outer],
    ]);
    $node->save();

    $inner = $this->reload($inner);

    // The viewer can view the host but cannot edit it. Delete on the
    // paragraph maps to 'update' on the host, so it must be denied.
    $this->assertTrue($inner->access('view', $viewer));
    $this->assertFalse($inner->access('update', $viewer));
    $this->assertFalse($inner->access('delete', $viewer));
  }

  /**
   * Tests that the view check honors the paragraph's own published status.
   *
   * Even when the user has full parent access, the paragraph's own
   * unpublished flag must still gate view access.
   *
   * @covers ::checkAccess
   */
  public function testCheckAccessUnpublishedInnerParagraph(): void {
    $this->setUpAccessFixtures();
    $editor = $this->createUser([
      'access content',
      'edit any paragraphed_test content',
    ]);

    $inner = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'inner',
      'status' => FALSE,
    ]);
    $inner->save();
    $outer = Paragraph::create([
      'type' => 'nested_paragraph',
      'field_nested' => [$inner],
    ]);
    $outer->save();
    $node = Node::create([
      'title' => 'Host',
      'type' => 'paragraphed_test',
      'field_paragraphs' => [$outer],
    ]);
    $node->save();

    $inner = $this->reload($inner);

    // Parent access is allowed for the editor, but the paragraph's own
    // unpublished status hides it from view.
    $this->assertFalse($inner->access('view', $editor));
    // Update isn't gated by published status, only by parent access.
    $this->assertTrue($inner->access('update', $editor));
    $this->assertTrue($inner->access('delete', $editor));
  }

  /**
   * Tests that access is checked against the parent revision referencing it.
   *
   * Issue #3090200: the access check must locate the parent revision that
   * actually references the paragraph (not just the default revision), so a
   * reverted or content-moderation-draft scenario doesn't strand paragraphs
   * behind an inaccessible default revision.
   *
   * Here we exercise the revision lookup directly: a node with two revisions
   * referencing two different paragraph revisions. We verify that loading
   * the older paragraph revision still finds the correct (older) node
   * revision for the access check.
   *
   * @covers ::resolveAccessHost
   * @covers ::loadReferencingRevision
   */
  public function testCheckAccessUsesReferencingRevision(): void {
    $this->setUpAccessFixtures();
    $editor = $this->createUser([
      'access content',
      'edit any paragraphed_test content',
    ]);

    $paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'v1',
    ]);
    $paragraph->save();
    $node = Node::create([
      'title' => 'Host',
      'type' => 'paragraphed_test',
      'field_paragraphs' => [$paragraph],
    ]);
    $node->save();
    $first_paragraph_revision_id = $paragraph->getRevisionId();
    $first_node_revision_id = $node->getRevisionId();

    // Create a new paragraph revision and a new node revision referencing it.
    $paragraph = $this->reload($paragraph);
    $paragraph->set('field_text', 'v2');
    $paragraph->setNewRevision(TRUE);
    $paragraph->save();
    $node = $this->reload($node);
    $node->set('field_paragraphs', [$paragraph]);
    $node->setNewRevision(TRUE);
    $node->save();
    $second_node_revision_id = $node->getRevisionId();

    $this->assertNotSame($first_node_revision_id, $second_node_revision_id);

    // Load the old paragraph revision and resolve its host. The resolver
    // must pick the node revision that actually references this paragraph
    // revision, not the default revision.
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('paragraph');
    $reflection = new \ReflectionMethod($handler, 'resolveAccessHost');
    $reflection->setAccessible(TRUE);
    $old_paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->loadRevision($first_paragraph_revision_id);

    $host = $reflection->invoke($handler, $old_paragraph);
    $this->assertNotNull($host);
    $this->assertSame('node', $host->getEntityTypeId());
    $this->assertSame($first_node_revision_id, $host->getRevisionId());

    // And the access decision still works correctly on that resolved host.
    $this->assertTrue($old_paragraph->access('view', $editor));
    $this->assertTrue($old_paragraph->access('update', $editor));
  }

  /**
   * Tests resolution against a content_moderation draft of the host.
   *
   * Issue #3090200's user-visible scenario: content_moderation makes the
   * published revision the default, so a newer draft revision (which
   * actually references the edited paragraph revision) is *not* what
   * getParentEntity() returns. The access check must still pick the draft
   * node revision, otherwise the paragraph appears under the wrong host
   * revision and edit/view decisions diverge from reality.
   *
   * @covers ::resolveAccessHost
   * @covers ::loadReferencingRevision
   */
  public function testCheckAccessUsesModeratedDraftRevision(): void {
    $this->setUpAccessFixtures();
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['content_moderation', 'filter']);
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'paragraphed_test');
    $workflow->save();

    // Published revision: node rev 1 references paragraph rev 1.
    $paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'v1',
    ]);
    $paragraph->save();
    $node = Node::create([
      'title' => 'Host',
      'type' => 'paragraphed_test',
      'field_paragraphs' => [$paragraph],
      'moderation_state' => 'published',
    ]);
    $node->save();
    $published_node_revision_id = $node->getRevisionId();

    // Draft revision: edit the paragraph and save the node as a draft.
    $paragraph = $this->reload($paragraph);
    $paragraph->set('field_text', 'v2');
    $paragraph->setNewRevision(TRUE);
    $paragraph->save();
    $draft_paragraph_revision_id = $paragraph->getRevisionId();
    $node = $this->reload($node);
    $node->set('field_paragraphs', [$paragraph]);
    $node->set('moderation_state', 'draft');
    $node->save();
    $draft_node_revision_id = $node->getRevisionId();
    $this->assertNotSame($published_node_revision_id, $draft_node_revision_id);

    // Default revision is still the published one — that's the moderation
    // bug surface area: getParentEntity() will load the *published* node.
    $default_node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadUnchanged($node->id());
    $this->assertSame($published_node_revision_id, $default_node->getRevisionId());

    // resolveAccessHost on the draft paragraph revision must pick the draft
    // node revision, not the default (published) one.
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('paragraph');
    $reflection = new \ReflectionMethod($handler, 'resolveAccessHost');
    $reflection->setAccessible(TRUE);
    $draft_paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->loadRevision($draft_paragraph_revision_id);

    $host = $reflection->invoke($handler, $draft_paragraph);
    $this->assertNotNull($host);
    $this->assertSame('node', $host->getEntityTypeId());
    $this->assertSame($draft_node_revision_id, $host->getRevisionId());
  }

  /**
   * Public-API counterpart to testCheckAccessUsesModeratedDraftRevision.
   *
   * Issue #3090200's regression surface is `$paragraph->access(...)` returning
   * the wrong answer on a moderation draft. The resolver-level test above
   * proves we *picked* the right node revision; this one proves that
   * decision reaches callers through the standard access API and tracks the
   * draft node revision's access rather than the default (published) one.
   *
   * @covers ::checkAccess
   */
  public function testAccessApiHonorsModeratedDraftRevision(): void {
    $this->setUpAccessFixtures();
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['content_moderation', 'filter']);
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'paragraphed_test');
    $workflow->save();

    $editor = $this->createUser([
      'access content',
      'edit any paragraphed_test content',
      'view any unpublished content',
      // content_moderation gates edit on having access to a transition out
      // of the current state.
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]);
    $stranger = $this->createUser(['access content']);

    $paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'v1',
    ]);
    $paragraph->save();
    $node = Node::create([
      'title' => 'Host',
      'type' => 'paragraphed_test',
      'field_paragraphs' => [$paragraph],
      'moderation_state' => 'published',
    ]);
    $node->save();

    $paragraph = $this->reload($paragraph);
    $paragraph->set('field_text', 'v2');
    $paragraph->setNewRevision(TRUE);
    $paragraph->save();
    $draft_paragraph_revision_id = $paragraph->getRevisionId();
    $node = $this->reload($node);
    $node->set('field_paragraphs', [$paragraph]);
    $node->set('moderation_state', 'draft');
    $node->save();
    $draft_node_revision_id = $node->getRevisionId();

    $draft_paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->loadRevision($draft_paragraph_revision_id);
    $draft_node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($draft_node_revision_id);

    // The editor can edit the draft node revision; therefore the public
    // access API on the draft paragraph revision must also allow update.
    // If we had regressed to checking the *default* (published) node
    // revision and node_access denied edits to it, these would diverge.
    $this->assertTrue($draft_node->access('update', $editor));
    $this->assertTrue($draft_paragraph->access('update', $editor));
    $this->assertTrue($draft_paragraph->access('delete', $editor));

    // Stranger has no edit permission; access must be denied through the
    // resolved (draft) host, not bypassed.
    $this->assertFalse($draft_paragraph->access('update', $stranger));
    $this->assertFalse($draft_paragraph->access('delete', $stranger));
  }

  /**
   * Tests that revisionable non-node hosts (block_content) are resolved too.
   *
   * The bug report explicitly calls out block_content + Layout Builder. The
   * fix should be host-agnostic; this test confirms loadReferencingRevision
   * works for a non-node revisionable host.
   *
   * @covers ::resolveAccessHost
   * @covers ::loadReferencingRevision
   */
  public function testCheckAccessUsesBlockContentRevision(): void {
    $this->setUpAccessFixtures();
    $this->installEntitySchema('block_content');

    \Drupal\block_content\Entity\BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
      'revision' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'block_content',
      'type' => 'entity_reference_revisions',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'block_content',
      'bundle' => 'basic',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => NULL],
      ],
    ])->save();

    $paragraph = Paragraph::create([
      'type' => 'text_paragraph',
      'field_text' => 'v1',
    ]);
    $paragraph->save();
    $block = \Drupal\block_content\Entity\BlockContent::create([
      'info' => 'Host block',
      'type' => 'basic',
      'field_paragraphs' => [$paragraph],
    ]);
    $block->save();
    $first_block_revision_id = $block->getRevisionId();
    $first_paragraph_revision_id = $paragraph->getRevisionId();

    // New paragraph revision attached to a new block revision.
    $paragraph = $this->reload($paragraph);
    $paragraph->set('field_text', 'v2');
    $paragraph->setNewRevision(TRUE);
    $paragraph->save();
    $block = $this->reload($block);
    $block->set('field_paragraphs', [$paragraph]);
    $block->setNewRevision(TRUE);
    $block->save();
    $second_block_revision_id = $block->getRevisionId();
    $this->assertNotSame($first_block_revision_id, $second_block_revision_id);

    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('paragraph');
    $reflection = new \ReflectionMethod($handler, 'resolveAccessHost');
    $reflection->setAccessible(TRUE);
    $old_paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->loadRevision($first_paragraph_revision_id);

    $host = $reflection->invoke($handler, $old_paragraph);
    $this->assertNotNull($host);
    $this->assertSame('block_content', $host->getEntityTypeId());
    $this->assertSame($first_block_revision_id, $host->getRevisionId());
  }

  /**
   * Reloads an entity from storage to pick up parent references on save.
   */
  protected function reload($entity) {
    return \Drupal::entityTypeManager()
      ->getStorage($entity->getEntityTypeId())
      ->loadUnchanged($entity->id());
  }

  /**
   * Installs schemas, fields, and types used by the checkAccess tests.
   */
  protected function setUpAccessFixtures(): void {
    $cache_contexts_manager = $this->prophesize(CacheContextsManager::class);
    $cache_contexts_manager->assertValidTokens()->willReturn(TRUE);
    $cache_contexts_manager->reveal();
    $this->container->set('cache_contexts_manager', $cache_contexts_manager);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['paragraphs']);

    // Reserve uid 1 so subsequent createUser() calls don't return the super
    // admin (whose access bypass would short-circuit every check below).
    $this->createUser([], NULL, TRUE);

    NodeType::create([
      'type' => 'paragraphed_test',
      'name' => 'paragraphed_test',
    ])->save();

    ParagraphsType::create([
      'id' => 'text_paragraph',
      'label' => 'text_paragraph',
    ])->save();
    ParagraphsType::create([
      'id' => 'nested_paragraph',
      'label' => 'nested_paragraph',
    ])->save();

    // Plain text field on text_paragraph.
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'text_paragraph',
    ])->save();

    // Nested paragraph reference on nested_paragraph.
    FieldStorageConfig::create([
      'field_name' => 'field_nested',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference_revisions',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_nested',
      'entity_type' => 'paragraph',
      'bundle' => 'nested_paragraph',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => NULL],
      ],
    ])->save();

    // Paragraphs reference field on the node.
    FieldStorageConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'bundle' => 'paragraphed_test',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => NULL],
      ],
    ])->save();
  }

}
