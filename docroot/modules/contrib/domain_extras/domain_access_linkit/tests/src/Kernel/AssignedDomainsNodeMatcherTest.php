<?php

declare(strict_types=1);

namespace Drupal\Tests\domain_access_linkit\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

/**
 * Tests the AssignedDomainsNodeMatcher Linkit plugin.
 *
 * @group domain_extras
 */
class AssignedDomainsNodeMatcherTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Core/system dependencies.
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'language',
    'path',
    'path_alias',
    // Linkit.
    'linkit',
    // Domain ecosystem.
    'domain',
    'domain_access',
    // Module under test (Linkit matcher).
    'domain_access_linkit',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required schemas/config.
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installConfig(static::$modules);

    // Create a simple content type.
    $type = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $type->save();

    // Ensure domain access fields are installed on node and user entities.
    \Drupal::moduleHandler()->loadInclude('domain_access', 'install');
    domain_access_install();

    // Create two domains used for assignment.
    $storage = $this->container->get('entity_type.manager')->getStorage('domain');
    $storage->create([
      'id' => 'one_example_com',
      'name' => 'one.example.com',
      'hostname' => 'one.example.com',
    ])->save();
    $storage->create([
      'id' => 'two_example_com',
      'name' => 'two.example.com',
      'hostname' => 'two.example.com',
    ])->save();
  }

  /**
   * Verifies the matcher only returns nodes on the user's assigned domains.
   */
  public function testAssignedDomainsMatcherFiltersByUserDomains(): void {
    // Create two published nodes that share a keyword in title.
    $node_one = Node::create([
      'type' => 'page',
      'title' => 'Alpha - One',
      'status' => TRUE,
      'field_domain_access' => [
        ['target_id' => 'one_example_com'],
      ],
    ]);
    $node_one->save();
    // Manually persist a node_access grant for the assigned domain so that
    // EntityQuery + per-entity access can both rely on grant rows.
    $this->writeDomainGrant($node_one, 'one_example_com');

    $node_two = Node::create([
      'type' => 'page',
      'title' => 'Alpha - Two',
      'status' => TRUE,
      'field_domain_access' => [
        ['target_id' => 'two_example_com'],
      ],
    ]);
    $node_two->save();
    $this->writeDomainGrant($node_two, 'two_example_com');

    // Create a user and assign them to the first domain.
    // Grant basic node view permission to the test user.
    $role = Role::create([
      'id' => 'content_viewer',
      'label' => 'Content viewer',
    ]);
    $role->save();
    user_role_grant_permissions(
      $role->id(),
      [
        'access content',
        'link to content on assigned domains',
      ]
    );

    $account = User::create([
      // Ensure the test user is not UID 1 (which implicitly bypasses access)
      'uid' => 2,
      'name' => 'matcher-user',
      'status' => 1,
      'roles' => [$role->id()],
      'field_domain_access' => [
        ['target_id' => 'one_example_com'],
      ],
    ]);
    $account->save();
    \Drupal::currentUser()->setAccount($account);

    // Set the active domain context to one_example_com so domain grants
    // are built for that domain during access checks.
    $negotiator = \Drupal::service('domain.negotiator');
    $domain_one = $this->container->get('entity_type.manager')->getStorage('domain')->load('one_example_com');
    $negotiator->setActiveDomain($domain_one);

    // Execute the matcher.
    $manager = $this->container->get('plugin.manager.linkit.matcher');
    /** @var \Drupal\linkit\MatcherInterface $plugin */
    $plugin = $manager->createInstance('node_assigned_domains');

    $suggestions = $plugin->execute('Alpha')->getSuggestions();

    // Only the node assigned to one_example_com should be returned.
    $this->assertCount(1, $suggestions, 'Only one suggestion returned for assigned domain.');
    $only = reset($suggestions);
    $this->assertStringContainsString('Alpha - One', $only->getLabel());

    // Reassign the user to the other domain and ensure results flip.
    $account->set('field_domain_access', [['target_id' => 'two_example_com']]);
    $account->save();

    // Switch active domain context to two_example_com.
    $domain_two = $this->container->get('entity_type.manager')->getStorage('domain')->load('two_example_com');
    $negotiator->setActiveDomain($domain_two);

    $suggestions2 = $plugin->execute('Alpha')->getSuggestions();
    $this->assertCount(1, $suggestions2, 'Still one suggestion after reassignment.');
    $only2 = reset($suggestions2);
    $this->assertStringContainsString('Alpha - Two', $only2->getLabel());

    // Now assign BOTH domains to the user and ensure both nodes appear.
    $account->set('field_domain_access', [
      ['target_id' => 'one_example_com'],
      ['target_id' => 'two_example_com'],
    ]);
    $account->save();

    // Active domain may remain as 'two_example_com'; temporary grants should
    // include both assigned domains regardless of active context.
    $suggestions3 = $plugin->execute('Alpha')->getSuggestions();
    $this->assertCount(2, $suggestions3, 'Two suggestions returned when user is assigned to both domains.');

    // Collect labels to assert presence of both nodes.
    $labels = array_map(static function ($s) {
      return $s->getLabel();
    }, $suggestions3);
    $this->assertTrue((bool) array_filter($labels, static fn($l) => str_contains($l, 'Alpha - One')));
    $this->assertTrue((bool) array_filter($labels, static fn($l) => str_contains($l, 'Alpha - Two')));
  }

  /**
   * Writes a node_access grant row for the given node and domain.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to grant access to.
   * @param string $domain_id
   *   The domain entity ID (e.g., 'one_example_com').
   */
  protected function writeDomainGrant(Node $node, string $domain_id): void {
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $this->container->get('entity_type.manager')->getStorage('domain')->load($domain_id);
    $gid = $domain->getDomainId();

    /** @var \Drupal\node\NodeGrantDatabaseStorageInterface $grant_storage */
    $grant_storage = \Drupal::service('node.grant_storage');
    $grant_storage->write($node, [[
      'realm' => 'domain_id',
      'gid' => $gid,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
      'langcode' => $node->language()->getId(),
    ],
    ], 'domain_id', TRUE);
  }

}
