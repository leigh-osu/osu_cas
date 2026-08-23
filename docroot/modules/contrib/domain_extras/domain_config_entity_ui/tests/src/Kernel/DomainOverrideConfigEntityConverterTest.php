<?php

namespace Drupal\Tests\domain_config_entity_ui\Kernel;

use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\Entity\Domain;
use Drupal\domain_config_entity_ui\ParamConverter\DomainOverrideConfigEntityConverter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Route;

/**
 * Isolated coverage for DomainOverrideConfigEntityConverter::convert().
 *
 * The converter sits at higher priority than core's
 * AdminPathConfigEntityConverter and is supposed to:
 *  * defer to the parent override-free behavior when the configuration
 *    is not registered for the active domain;
 *  * load the entity through entityTypeManager->getStorage()->load()
 *    (with overrides) when the configuration is registered for the
 *    active domain.
 *
 * Each path is asserted in isolation here so a regression on the
 * registration check is caught without going through the full
 * functional EntityForm round-trip in DomainConfigUiEntityFormTest.
 *
 * Installing this submodule is the opt-in — there is no separate
 * runtime flag.
 *
 * @group domain_config_entity_ui
 *
 * @see \Drupal\domain_config_entity_ui\ParamConverter\DomainOverrideConfigEntityConverter
 * @see https://www.drupal.org/project/domain_extras/issues/3588091
 */
#[Group('domain_config_entity_ui')]
#[RunTestsInSeparateProcesses]
class DomainOverrideConfigEntityConverterTest extends KernelTestBase {

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
   * Domain id used by all tests.
   */
  private const DOMAIN_ID = 'example_com';

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

    // Cover `block` so the storage handler is the DomainAware variant
    // — the converter's capability gate (storage instanceof
    // DomainAwareConfigEntityStorageInterface) needs that to act.
    $this->container->get('config.factory')
      ->getEditable('domain_config_entity_ui.settings')
      ->set('covered_entity_types', ['block'])
      ->save();
    $this->container->get('entity_type.manager')->clearCachedDefinitions();

    Domain::create([
      'id' => self::DOMAIN_ID,
      'hostname' => 'example.com',
      'name' => 'Example',
      'scheme' => 'http',
      'status' => 1,
      'weight' => 0,
      'is_default' => TRUE,
    ])->save();

    Block::create([
      'id' => self::BLOCK_ID,
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'content',
      'settings' => ['label' => 'Default label', 'label_display' => 'visible'],
    ])->save();

    // Push a request matching the test domain so the negotiator binds it.
    $request = Request::create('http://example.com/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $this->container->get('domain.negotiator')->getActiveDomain(TRUE);
  }

  /**
   * Config not registered: defer to parent override-free behavior.
   *
   * Per the registration contract, the override-merged load only kicks
   * in when the configuration is explicitly registered for the active
   * domain — an override row sitting in storage without a matching
   * registration must still resolve to base.
   */
  public function testConfigNotRegisteredDefersToParent(): void {
    $this->writeOverride('Override label');

    $entity = $this->convertBlock();
    self::assertNotNull($entity, 'Converter returns the entity.');
    self::assertSame(
      'Default label',
      $entity->label(),
      'Config not registered: returns base.'
    );
  }

  /**
   * Config registered: returns the override-merged entity.
   *
   * The expected end state: the converter routes the load through the
   * entity_type_manager's storage with overrides applied, so the
   * returned entity carries the per-domain values the EntityForm
   * should render.
   */
  public function testConfigRegisteredReturnsOverrideMerged(): void {
    $this->writeOverride('Override label');
    $this->container->get('domain_config_ui.manager')
      ->addConfigurationsToDomain(self::DOMAIN_ID, [self::BLOCK_CONFIG_NAME]);

    $entity = $this->convertBlock();
    self::assertSame(
      'Override label',
      $entity->label(),
      'Config registered: returns override-merged entity.'
    );
  }

  /**
   * Writes a label override on the test block for the test domain.
   */
  private function writeOverride(string $label): void {
    $factory = $this->container->get('domain.config_factory_override');
    $factory->getOverrideEditable(self::DOMAIN_ID, self::BLOCK_CONFIG_NAME)
      ->set('settings.label', $label)
      ->save();
  }

  /**
   * Runs the converter against the test block.
   */
  private function convertBlock(): mixed {
    $converter = $this->container->get('domain_config_entity_ui.paramconverter.configentity_admin');
    return $converter->convert(self::BLOCK_ID, ['type' => 'entity:block'], 'block', []);
  }

  /**
   * Claims the route for entity types backed by domain-aware storage.
   *
   * Block storage is wrapped because covered_entity_types includes 'block'
   * in setUp(), so the converter is responsible for the route and
   * applies() must return TRUE.
   */
  public function testAppliesTrueForDomainAwareStorage(): void {
    $route = $this->makeAdminRoute('/admin/structure/block/manage/{block}');
    $applies = $this->converter()
      ->applies(['type' => 'entity:block'], 'block', $route);
    self::assertTrue(
      $applies,
      'applies() returns TRUE for entity types with domain-aware storage.'
    );
  }

  /**
   * Declines the route when the entity type's storage is not domain-aware.
   *
   * Regression for #3589233: applies() used to return TRUE for every
   * entity:* parameter on an admin path, tying on priority 10 with
   * views_ui's ViewUIConverter and displacing it at route-rebuild time
   * even though convert() would just defer to parent::convert(). The
   * View edit form then crashed with a TypeError on a bare View entity.
   * Date format storage is plain ConfigEntityStorage, so it must fall
   * through to the rest of the converter chain unchanged.
   */
  public function testAppliesFalseForNonDomainAwareStorage(): void {
    $route = $this->makeAdminRoute('/admin/config/regional/date-time/formats/manage/{date_format}');
    $applies = $this->converter()
      ->applies(['type' => 'entity:date_format'], 'date_format', $route);
    self::assertFalse(
      $applies,
      'applies() returns FALSE for entity types whose storage is not domain-aware.'
    );
  }

  /**
   * Declines dynamic entity types because the storage class is unknown.
   *
   * The {entity_type} placeholder cannot be resolved at applies()-time
   * without the request's defaults, so the converter declines and lets
   * the rest of the chain pick the right converter at runtime.
   */
  public function testAppliesFalseForDynamicEntityType(): void {
    $route = $this->makeAdminRoute('/admin/config/some/{entity_type}/{entity}');
    $route->setDefault('entity_type', 'block');
    $applies = $this->converter()
      ->applies(['type' => 'entity:{entity_type}'], 'entity', $route);
    self::assertFalse(
      $applies,
      'applies() returns FALSE for dynamic entity:{entity_type} parameters.'
    );
  }

  /**
   * Returns the converter under test.
   */
  private function converter(): DomainOverrideConfigEntityConverter {
    return $this->container->get('domain_config_entity_ui.paramconverter.configentity_admin');
  }

  /**
   * Builds an admin Route at the given path.
   */
  private function makeAdminRoute(string $path): Route {
    $route = new Route($path);
    $route->setOption('_admin_route', TRUE);
    return $route;
  }

}
