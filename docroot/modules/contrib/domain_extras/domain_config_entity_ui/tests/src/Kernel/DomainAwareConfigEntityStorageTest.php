<?php

namespace Drupal\Tests\domain_config_entity_ui\Kernel;

use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\Entity\Domain;
use Drupal\domain_config\Config\DomainConfigOverrideEditable;
use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorage;
use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Kernel coverage for domain_config_entity_ui.
 *
 * Asserts the behaviors we promise on this submodule:
 *  1. The entity_type_alter swap kicks in on install for curated
 *     entity types whose default storage_class is the vanilla
 *     ConfigEntityStorage (block today). Other entity types stay on
 *     their own storage handler — capability discovery via the marker
 *     interface is the contract the form_alter and converter rely on.
 *  2. DomainAwareConfigEntityStorage::loadOverrideFree() folds the
 *     active domain's override on top for configurations registered
 *     for that domain, returns base for everything else, and
 *     short-circuits when no domain is bound (CLI / install hooks
 *     stay strictly base).
 *  3. The block-list / submitForm regression (#3587744) stays fixed:
 *     loading through the now-domain-aware block storage returns
 *     override-merged values, and a save through the regular
 *     entity_type_manager save path lands a sparse override row that
 *     preserves the prior label override alongside the newly
 *     differing weight.
 *  4. A re-read after an override save within the same request
 *     reflects the new value (no stale entity in @entity.memory_cache
 *     because onConfigSave invalidates the storage instance core
 *     hands out, which is now ours).
 *
 * @see https://www.drupal.org/project/domain_extras/issues/3588091
 *
 * @group domain_config_entity_ui
 */
#[Group('domain_config_entity_ui')]
#[RunTestsInSeparateProcesses]
class DomainAwareConfigEntityStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'domain',
    'domain_config',
    'domain_config_ui',
    'domain_config_entity_ui',
    'language',
    'system',
    'user',
  ];

  /**
   * Active domain used by all tests below.
   */
  private const ACTIVE_DOMAIN_ID = 'a_example_com';

  /**
   * Block id placed in setUp() for the override scenarios.
   */
  private const BLOCK_ID = 'test_powered_by';

  /**
   * Config name of the test block, derived from the entity prefix.
   */
  private const BLOCK_CONFIG_NAME = 'block.block.test_powered_by';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'block', 'domain_config_ui', 'domain_config_entity_ui']);

    // The submodule swaps storage_class only for entity types the user
    // has explicitly enabled via the SettingsForm. Tests below operate
    // on `block`, so flip its checkbox programmatically and force a
    // fresh entity-type-definitions read so the alter hook re-applies
    // against the new selection.
    $this->container->get('config.factory')
      ->getEditable('domain_config_entity_ui.settings')
      ->set('covered_entity_types', ['block'])
      ->save();
    $this->container->get('entity_type.manager')->clearCachedDefinitions();

    Domain::create([
      'id' => self::ACTIVE_DOMAIN_ID,
      'hostname' => 'a.example.com',
      'name' => 'A',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ])->save();
    Domain::create([
      'id' => 'b_example_com',
      'hostname' => 'b.example.com',
      'name' => 'B',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 1,
      'is_default' => FALSE,
    ])->save();

    Block::create([
      'id' => self::BLOCK_ID,
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'content',
      'settings' => ['label' => 'Default label', 'label_display' => 'visible'],
    ])->save();

    // Push a request matching the active domain so the negotiator
    // resolves to it, then force binding. A mock session is attached
    // because some subscribers read the session on kernel.request.
    $request = Request::create('http://a.example.com/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $this->container->get('domain.negotiator')->getActiveDomain(TRUE);
  }

  /**
   * Block storage_class is swapped to the DomainAware variant on install.
   *
   * Installing the submodule is the opt-in. The swap must apply to
   * curated entity types only — block today, future ones added via
   * sibling subclasses + map entries. The marker interface is the
   * contract by which form_alter / ParamConverter discover coverage.
   */
  public function testBlockStorageIsSwappedOnInstall(): void {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('block');
    self::assertSame(
      DomainAwareConfigEntityStorage::class,
      $entity_type->getStorageClass(),
      'Block entity type storage_class is swapped to the DomainAware variant.'
    );
    $storage = $this->container->get('entity_type.manager')->getStorage('block');
    self::assertInstanceOf(
      DomainAwareConfigEntityStorageInterface::class,
      $storage,
      'getStorage(block) returns a DomainAwareConfigEntityStorageInterface instance.'
    );
  }

  /**
   * Non-curated entity types stay on their own storage handler.
   *
   * The strict-equality guard in entityTypeAlter() must preserve any
   * non-vanilla storage_class set by core or contrib. user_role uses
   * RoleStorage which we have not curated yet, so it must NOT be a
   * DomainAware* instance — and therefore the form_alter must decline
   * to expose the toggle on its edit form.
   */
  public function testNonCuratedEntityTypeIsNotDomainAware(): void {
    $this->enableModules(['user']);
    $storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    self::assertNotInstanceOf(
      DomainAwareConfigEntityStorageInterface::class,
      $storage,
      'user_role keeps its own RoleStorage; capability discovery declines coverage.'
    );
  }

  /**
   * Disabling a covered entity type via the SettingsForm drops the swap.
   *
   * The reverse of the install flow: if a user un-checks a previously
   * covered entity type, the next entity-type-definitions read must
   * resolve back to the type's original storage_class. The settings
   * subscriber clears the entity-type-definitions cache on save so
   * this happens automatically.
   */
  public function testDisablingEntityTypeDropsTheSwap(): void {
    self::assertInstanceOf(
      DomainAwareConfigEntityStorageInterface::class,
      $this->container->get('entity_type.manager')->getStorage('block'),
      'Sanity: block is covered after setUp.'
    );

    $this->container->get('config.factory')
      ->getEditable('domain_config_entity_ui.settings')
      ->set('covered_entity_types', [])
      ->save();

    self::assertNotInstanceOf(
      DomainAwareConfigEntityStorageInterface::class,
      $this->container->get('entity_type.manager')->getStorage('block'),
      'Block storage drops back to vanilla ConfigEntityStorage on the next definitions read.'
    );
  }

  /**
   * Override-free reads honour the active domain's registered overrides.
   *
   * The override-free read folds the domain layer back on for
   * configurations registered as overridable for the active domain,
   * leaves base untouched for unregistered ones, and short-circuits
   * when no domain is bound (so CLI / install hooks see strictly base).
   */
  public function testLoadOverrideFreeFoldsRegisteredOverrideForActiveDomain(): void {
    $this->writeOverride(self::ACTIVE_DOMAIN_ID, 'Active label');
    $this->writeOverride('b_example_com', 'Other label');

    // No domain registered yet: returns strict base on every domain.
    $storage = $this->getBlockStorage();
    self::assertSame(
      'Default label',
      $storage->loadOverrideFree(self::BLOCK_ID)->label(),
      'Unregistered config: returns base regardless of the override.'
    );

    // Register for the active domain only.
    $this->container->get('domain_config_ui.manager')
      ->addConfigurationsToDomain(self::ACTIVE_DOMAIN_ID, [self::BLOCK_CONFIG_NAME]);
    $storage->resetCache([self::BLOCK_ID]);
    self::assertSame(
      'Active label',
      $storage->loadOverrideFree(self::BLOCK_ID)->label(),
      'Registered + active domain: returns the override-merged label.'
    );

    // Negotiate the *other* domain; its override is not registered, so
    // it does not apply, even though its row exists in storage.
    $this->container->get('domain.negotiator')
      ->setActiveDomain(Domain::load('b_example_com'));
    $storage->resetCache([self::BLOCK_ID]);
    self::assertSame(
      'Default label',
      $storage->loadOverrideFree(self::BLOCK_ID)->label(),
      'Active domain that is not registered: returns base.'
    );
  }

  /**
   * Block list-style save flow preserves the existing label override.
   *
   * Reproduces the latent bug surfaced on the block layout "Save blocks"
   * action: with a label override already in place, mutating weight via
   * an override-free load → save round-trip used to drop the label
   * override entirely. With storage_class swapped to the DomainAware
   * variant, the override-free load returns override-merged data, so
   * the diff bridge in DomainConfigOverrideEditable::save() lands a
   * sparse row that carries BOTH the prior label override and the
   * newly differing weight.
   */
  public function testBlockListSaveFlowKeepsLabelOverrideAndAddsWeight(): void {
    // The end-to-end behavior asserted below depends on the diff
    // bridge added in https://www.drupal.org/project/domain/issues/3587744
    // (parent project, domain_config). The cheapest signal that the
    // parent MR is present is the new $baseData property on the
    // editable — older releases of domain_config don't declare it
    // and would silently drop the weight on the save() path, so skip
    // rather than fail.
    if (!property_exists(DomainConfigOverrideEditable::class, 'baseData')) {
      self::markTestSkipped('Requires the diff bridge from #3587744 in domain_config.');
    }

    $this->container->get('domain_config_ui.manager')
      ->addConfigurationsToDomain(self::ACTIVE_DOMAIN_ID, [self::BLOCK_CONFIG_NAME]);
    $this->writeOverride(self::ACTIVE_DOMAIN_ID, 'Override label');

    $storage = $this->getBlockStorage();
    $block = $storage->loadOverrideFree(self::BLOCK_ID);
    self::assertSame('Override label', $block->label(), 'Override-free load gets override-merged label.');

    $block->setWeight(7);
    $block->save();

    $stored = $this->container->get('domain.config_factory_override')
      ->getStorage(self::ACTIVE_DOMAIN_ID)
      ->read(self::BLOCK_CONFIG_NAME);
    self::assertIsArray($stored);
    self::assertSame(
      'Override label',
      $stored['settings']['label'] ?? NULL,
      'Pre-existing label override survives the override-free load → save round-trip.'
    );
    self::assertSame(
      7,
      $stored['weight'] ?? NULL,
      'Weight change lands in the override row alongside the existing label override.'
    );
  }

  /**
   * Mid-request override edits are reflected on the next read.
   *
   * Drupal core's ConfigEntityStorage::onConfigSave() invalidates the
   * storage instance the entity_type_manager hands out. With the
   * storage_class swap in place that instance is OUR DomainAware
   * variant, so the invalidation reaches us correctly and the next
   * loadOverrideFree() reflects the new override value.
   */
  public function testRereadAfterOverrideSaveReflectsNewValue(): void {
    $this->container->get('domain_config_ui.manager')
      ->addConfigurationsToDomain(self::ACTIVE_DOMAIN_ID, [self::BLOCK_CONFIG_NAME]);

    $storage = $this->getBlockStorage();
    $this->writeOverride(self::ACTIVE_DOMAIN_ID, 'First label');
    self::assertSame('First label', $storage->loadOverrideFree(self::BLOCK_ID)->label());

    $this->writeOverride(self::ACTIVE_DOMAIN_ID, 'Second label');
    self::assertSame(
      'Second label',
      $storage->loadOverrideFree(self::BLOCK_ID)->label(),
      'A re-read on the same DomainAware storage reflects the new override.'
    );
  }

  /**
   * Resolves the (now domain-aware) block storage.
   */
  private function getBlockStorage(): DomainAwareConfigEntityStorage {
    $storage = $this->container->get('entity_type.manager')->getStorage('block');
    self::assertInstanceOf(DomainAwareConfigEntityStorage::class, $storage);
    return $storage;
  }

  /**
   * Writes a sparse label override into the per-domain collection.
   */
  private function writeOverride(string $domain_id, string $label): void {
    $factory = $this->container->get('domain.config_factory_override');
    $override = $factory->getOverrideEditable($domain_id, self::BLOCK_CONFIG_NAME);
    $override->set('settings.label', $label);
    $override->save();
  }

}
