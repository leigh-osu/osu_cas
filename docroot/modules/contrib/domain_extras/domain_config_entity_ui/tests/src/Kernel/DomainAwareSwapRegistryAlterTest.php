<?php

namespace Drupal\Tests\domain_config_entity_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\domain_config_entity_ui\DomainAwareSwapRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Coverage for hook_domain_config_entity_ui_swaps_alter.
 *
 * The alter hook is the documented extensibility point for contrib
 * modules to register their own DomainAware* storage subclasses
 * (see domain_config_entity_ui.api.php). The swap registry must:
 *  1. Auto-populate vanilla-storage entity types regardless of any
 *     alter implementation (block, view, search_page, …).
 *  2. Surface alter-registered entries to consumers (the SettingsForm
 *     and the entity_type_alter swap loop).
 *  3. Let alter implementations override the auto-populated entry
 *     for an entity type — last writer wins, so contrib can chain
 *     on top of the default DomainAware shell when its own subclass
 *     is more appropriate.
 *  4. NOT actually apply contrib swaps unless the strict-equality
 *     guard matches: the entity_type_alter swap loop only sets the
 *     storage_class when the type's current class equals the
 *     registered "expected current class". A contrib registration
 *     for an entity type whose handler is different stays in the
 *     registry but never mutates the type definition.
 *
 * @see https://www.drupal.org/project/domain_extras/issues/3588091
 *
 * @group domain_config_entity_ui
 */
#[Group('domain_config_entity_ui')]
#[RunTestsInSeparateProcesses]
class DomainAwareSwapRegistryAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'domain',
    'domain_config',
    'domain_config_ui',
    'domain_config_entity_ui',
    'domain_config_entity_ui_swaps_test',
    'language',
    'system',
    'user',
  ];

  /**
   * The alter-registered entry surfaces in the registry map.
   *
   * The test module's alter implementation registers a fake
   * user_role swap. The registry must expose it alongside the
   * auto-populated vanilla types — that is what the SettingsForm
   * iterates to render checkboxes, so without this the user could
   * never opt into a contrib-shipped subclass.
   */
  public function testAlterRegisteredEntrySurfacesInRegistry(): void {
    $registry = $this->container->get(DomainAwareSwapRegistry::class);
    $swaps = $registry->getSwaps();

    self::assertArrayHasKey('user_role', $swaps, 'Alter-registered entity type lands in the registry.');
    self::assertSame(
      [
        'Drupal\\domain_config_entity_ui_swaps_test\\Entity\\FakeDomainAwareRoleStorage',
        'Drupal\\user\\RoleStorage',
      ],
      $swaps['user_role'],
      'Alter-registered tuple is preserved verbatim — domain-aware class first, expected current class second.',
    );
  }

  /**
   * Auto-populated entries co-exist with alter-registered ones.
   *
   * Both layers contribute to the final map. The alter does NOT
   * replace the auto-populated set; it adds to it.
   */
  public function testAutoPopulationCoexistsWithAlter(): void {
    $registry = $this->container->get(DomainAwareSwapRegistry::class);
    $swaps = $registry->getSwaps();

    self::assertArrayHasKey('block', $swaps, 'Auto-populated vanilla entry survives alongside the alter-registered one.');
    self::assertArrayHasKey('user_role', $swaps);
  }

  /**
   * The strict-equality guard does NOT apply contrib swaps wholesale.
   *
   * The fake user_role registration declares ImageStyleStorage as
   * the expected current class. The test environment ships
   * RoleStorage instead, so the guard fails and the swap is never
   * applied — user_role's storage_class stays at its real value.
   * Without that guard, an unrelated alter implementation could
   * replace any entity type's handler with a class that has nothing
   * to do with it.
   */
  public function testContribSwapNotAppliedWhenExpectedCurrentClassMismatches(): void {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('user_role');
    self::assertSame(
      'Drupal\\user\\RoleStorage',
      $entity_type->getStorageClass(),
      'user_role keeps its real RoleStorage; alter registration does not force-apply a mismatched class.',
    );
  }

}
