<?php

namespace Drupal\Tests\block_visibility_groups\Functional;

use Drupal\block_visibility_groups\BlockVisibilityGroupInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the block_visibility_groups Visibility Settings.
 *
 * @group block_visibility_groups
 */
#[RunTestsInSeparateProcesses]
class VisibilityTest extends BlockVisibilityGroupsTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'block_visibility_groups',
    'node',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a Basic page node type.
    $this->drupalCreateContentType(
      [
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]
    );
    // Create a second, distinct node type so tests can assert that a
    // context-aware condition on the "page" bundle does not match.
    $this->drupalCreateContentType(
      [
        'type' => 'article',
        'name' => 'Article',
        'display_submitted' => FALSE,
      ]
    );
    // Allow both roles to view content and taxonomy term pages so blocks
    // actually render on those routes. The testing profile grants anonymous
    // nothing by default, so without this any assertion made after
    // drupalLogout() would be checked against a 403 page and would pass
    // vacuously instead of exercising the visibility logic.
    user_role_grant_permissions(RoleInterface::AUTHENTICATED_ID, ['access content']);
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);
  }

  /**
   * Test single conditions.
   */
  public function testSingleConditions() {
    $group = $this->createGroup([
      [
        'id' => 'entity_bundle:node',
        'bundles' => ['page' => 'page'],
        'context_mapping' => [
          'node' => '@node.node_route_context:node',
        ],
        'negate' => FALSE,
      ],
    ]);

    // Assert node type condition.
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Block is rendered when expected.
    $page_node = $this->drupalCreateNode();
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Block is NOT rendered when expected not to.
    $this->drupalGet('user');
    $this->assertSession()->pageTextNotContains($block->label());
    $block->delete();

    // Assert path condition.
    $this->deleteAllConditions($group);
    $group->addCondition([
      'id' => 'request_path',
      'pages' => '/node/*',
      'negate' => FALSE,
    ]);
    $group->save();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Block is rendered when expected.
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalGet('user');
    $this->assertSession()->pageTextNotContains($block->label());
    $block->delete();

    // Assert user condition.
    $this->deleteAllConditions($group);
    $group->addCondition([
      'id' => 'user_role',
      'roles' => ['authenticated'],
      'context_mapping' => [
        'user' => '@user.current_user_context:current_user',
      ],
      'negate' => FALSE,
    ]);
    $group->save();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Block is rendered when expected.
    $this->drupalGet('user');
    $this->assertSession()->pageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalLogout();
    $this->assertSession()->pageTextNotContains($block->label());

    // After uninstall conditions will not apply.
    $this->container->get('module_installer')->uninstall(['block_visibility_groups']);
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());
    $this->drupalGet('user');
    $this->assertSession()->pageTextContains($block->label());
  }

  /**
   * Test multiple conditions with AND and OR operators.
   */
  public function testMultipleConditions() {
    // Operator AND (by default).
    $group = $this->createGroup([
      [
        'id' => 'entity_bundle:node',
        'bundles' => ['page' => 'page'],
        'context_mapping' => [
          'node' => '@node.node_route_context:node',
        ],
        'negate' => FALSE,
      ],
      [
        'id' => 'user_role',
        'roles' => ['authenticated'],
        'context_mapping' => [
          'user' => '@user.current_user_context:current_user',
        ],
        'negate' => FALSE,
      ],
    ]);
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());
    $page_node = $this->drupalCreateNode();

    // Block is rendered when expected.
    $this->drupalGet($page_node->toUrl());
    $this->assertPageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalGet('user');
    $this->assertSession()->pageTextNotContains($block->label());
    $this->drupalLogout();
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());

    // Operator OR.
    $group->setLogic('or');
    $group->save();

    // Block is rendered when expected.
    $this->drupalGet($page_node->toUrl());
    $this->assertPageTextContains($block->label());
    $this->drupalLogin($this->blockVisibilityGroupsUser);
    $this->drupalGet('user');
    $this->assertPageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalLogout();
    $this->drupalGet('user');
    $this->assertSession()->pageTextNotContains($block->label());
  }

  /**
   * OR logic must not fail because a *different* condition lacks context.
   *
   * A content-type condition OR a request-path condition. On "/user/*" the node
   * context is null; before the fix the whole group returned FALSE and the
   * block disappeared even though the path condition matched.
   */
  public function testOrLogicWithRequestPathAndBundle() {
    $group = $this->createGroup([
      [
        'id' => 'entity_bundle:node',
        'bundles' => ['page' => 'page'],
        'context_mapping' => [
          'node' => '@node.node_route_context:node',
        ],
        'negate' => FALSE,
      ],
      [
        'id' => 'request_path',
        'pages' => '/user/*',
        'negate' => FALSE,
      ],
    ], ['logic' => 'or']);
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());
    $page_node = $this->drupalCreateNode(['type' => 'page']);

    // Shows on a "page" node: the bundle condition matches (the path condition
    // simply does not match, which is fine under OR).
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Shows on "/user/*": the path condition matches even though the node
    // context is null. This is the exact scenario that regressed. Note the
    // user's canonical URL is "/user/{uid}", which matches the "/user/*"
    // pattern; a bare "/user" would not, so do not swap in drupalGet('user').
    $this->drupalGet($this->blockVisibilityGroupsUser->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Hidden where neither condition matches: an "article" node is not a
    // "page" bundle and its path is not "/user/*".
    $article_node = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalGet($article_node->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());
  }

  /**
   * OR logic across two mutually-exclusive context-aware conditions.
   *
   * A content-type condition OR a taxonomy vocabulary condition. On a node page
   * the term context is null, and on a term page the node context is null;
   * neither missing context should cancel the group under OR.
   */
  public function testOrLogicWithMutuallyExclusiveContexts() {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $term = Term::create(['vid' => 'tags', 'name' => 'Term 1']);
    $term->save();

    $group = $this->createGroup([
      [
        'id' => 'entity_bundle:node',
        'bundles' => ['page' => 'page'],
        'context_mapping' => [
          'node' => '@node.node_route_context:node',
        ],
        'negate' => FALSE,
      ],
      [
        'id' => 'entity_bundle:taxonomy_term',
        'bundles' => ['tags' => 'tags'],
        'context_mapping' => [
          'taxonomy_term' => '@taxonomy_term.taxonomy_term_route_context:taxonomy_term',
        ],
        'negate' => FALSE,
      ],
    ], ['logic' => 'or']);
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());
    $page_node = $this->drupalCreateNode(['type' => 'page']);

    // Shows on the node page: node bundle matches, term context null skipped.
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Shows on the term page: term bundle matches, node context null skipped.
    $this->drupalGet($term->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Hidden on "/user/*": both contexts are null, so every condition is
    // skipped and none remains testable. This assertion looks redundant but
    // is not — it guards against an over-correction that treats a skipped
    // condition as passing, which would make the block leak onto every page.
    $this->drupalGet($this->blockVisibilityGroupsUser->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());
  }

  /**
   * AND logic treats a negated condition with a broken mapping differently.
   *
   * A context mapping that can never be satisfied — the stale config left
   * behind when a context provider changes or its module is uninstalled —
   * makes applyContextMapping() throw a ContextException. Under AND logic a
   * negated condition is then discarded so the remaining conditions decide,
   * whereas a non-negated one fails the group closed.
   */
  public function testAndLogicWithUnsatisfiableContextMapping() {
    $path_condition = [
      'id' => 'request_path',
      'pages' => '/user/*',
      'negate' => FALSE,
    ];
    // The context provider service exists but offers no context by this name,
    // so the mapping can never be satisfied.
    $broken_condition = [
      'id' => 'entity_bundle:node',
      'bundles' => ['page' => 'page'],
      'context_mapping' => [
        'node' => '@node.node_route_context:no_such_context',
      ],
      'negate' => TRUE,
    ];
    $group = $this->createGroup(
      [$path_condition, $broken_condition],
      ['logic' => 'and']
    );
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Negated: the unsatisfiable condition is discarded instead of failing the
    // group, so the matching path condition alone decides and the block shows.
    $this->drupalGet($this->blockVisibilityGroupsUser->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Not negated: the identical broken mapping must fail the group closed.
    $this->deleteAllConditions($group);
    $broken_condition['negate'] = FALSE;
    $group->addCondition($path_condition);
    $group->addCondition($broken_condition);
    $group->save();

    $this->drupalGet($this->blockVisibilityGroupsUser->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());
  }

  /**
   * A skipped (missing-context) condition must not leak a false positive.
   *
   * This is a guard rather than a regression test, and passes both before and
   * after the OR context fix. It pins down that a condition skipped for
   * missing context still resolves to FALSE, so that a later change cannot
   * turn "skipped" into "matched" while another condition remains testable.
   */
  public function testOrLogicNoMatchStaysHidden() {
    $group = $this->createGroup([
      [
        'id' => 'entity_bundle:node',
        'bundles' => ['page' => 'page'],
        'context_mapping' => [
          'node' => '@node.node_route_context:node',
        ],
        'negate' => FALSE,
      ],
      [
        'id' => 'request_path',
        'pages' => '/this-path-never-matches/*',
        'negate' => FALSE,
      ],
    ], ['logic' => 'or']);
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // On "/user/*": the node context is null (bundle condition skipped) and the
    // path condition does not match. The block must remain hidden.
    $this->drupalGet($this->blockVisibilityGroupsUser->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());
  }

  /**
   * Helper to delete all conditions of a block visibility group.
   *
   * @param \Drupal\block_visibility_groups\BlockVisibilityGroupInterface $group
   *   BlockVisibilityGroup instance.
   */
  protected function deleteAllConditions(BlockVisibilityGroupInterface $group) {
    foreach ($group->getConditions() as $condition) {
      $group->removeCondition($condition->getConfiguration()['uuid']);
    }
    $group->save();
  }

}
