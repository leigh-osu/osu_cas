<?php

declare(strict_types=1);

namespace Drupal\Tests\domain_config_entity_ui\Functional;

use Drupal\block\Entity\Block;
use Drupal\domain_config\Config\DomainConfigOverrideEditable;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config\Functional\DomainConfigTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain config toggle on config-entity edit forms.
 *
 * Installing this submodule is the opt-in — there is no separate
 * runtime flag. Once it is enabled, the parent module's "Enable
 * domain configuration" toggle appears on EntityForm-based config
 * entity edit forms (block, view mode, search page, …) on a domain
 * context, and saves through ConfigEntityStorage::doSave() land in
 * the per-domain override.
 *
 * @group domain_config_entity_ui
 *
 * @see https://www.drupal.org/project/domain_extras/issues/3588091
 */
#[Group('domain_config_entity_ui')]
#[RunTestsInSeparateProcesses]
class DomainConfigEntityUiToggleTest extends DomainConfigTestBase {

  use DomainTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'domain_config_ui',
    'domain_config_entity_ui',
    'user',
  ];

  /**
   * Admin user with permission to manage blocks and domain config UI.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setBaseHostname();
    $this->domainCreateTestDomains(2);

    // Enable per-domain support on block — the type the rest of this
    // class exercises. The settings subscriber will clear the entity
    // type definitions cache on save so the storage_class swap takes
    // effect on the next request.
    $this->config('domain_config_entity_ui.settings')
      ->set('covered_entity_types', ['block'])
      ->save();

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer domain config ui',
      'administer domains',
      'administer site configuration',
      'administer themes',
      'set default domain configuration',
      'use domain config ui',
      'view domain information',
    ]);
  }

  /**
   * Tests that the toggle appears on the Configure block form.
   *
   * The Configure block form uses BlockForm which extends EntityForm,
   * not ConfigFormBase. Without this submodule the parent's form_alter
   * never injects the "Enable domain configuration" toggle on this
   * form, even though block.block.* configs are otherwise
   * domain-overridable. With the submodule installed the toggle
   * appears whenever an active domain is bound to the request.
   */
  public function testToggleAppearsOnBlockForm(): void {
    Block::create([
      'id' => 'test_powered_by',
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'content',
      'settings' => ['label' => 'Powered'],
    ])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/block/manage/test_powered_by');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Enable domain configuration');
  }

  /**
   * Tests that no toggle appears on the Place block form (new entity).
   *
   * The toggle requires a non-empty config name to register. A new
   * unsaved block has no id, so its config dependency name is invalid.
   * The form_alter guard "!isNew()" must skip the toggle in that case.
   */
  public function testNoToggleOnPlaceBlockForm(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/block/add/system_powered_by_block/stark');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists('Enable domain configuration');
  }

  /**
   * Tests that the SettingsForm flips coverage on the next request.
   *
   * The full UX path: an admin lands on /admin/config/domain/config-entity-ui,
   * checks a covered entity type, and the toggle appears on that
   * type's edit form on the next page render. The settings subscriber
   * is what makes that possible by clearing the entity type
   * definitions cache on save — without it the user would have to
   * `drush cr`.
   */
  public function testSettingsFormFlipsCoverageOnNextRequest(): void {
    // Reset coverage to empty; then re-test that the toggle is gone.
    $this->config('domain_config_entity_ui.settings')
      ->set('covered_entity_types', [])
      ->save();

    Block::create([
      'id' => 'test_powered_by',
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'content',
      'settings' => ['label' => 'Powered'],
    ])->save();

    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer domain config entity ui',
      'administer domain config ui',
      'administer domains',
      'set default domain configuration',
      'use domain config ui',
      'view domain information',
    ]);
    $this->drupalLogin($admin);

    // No coverage → no toggle.
    $this->drupalGet('/admin/structure/block/manage/test_powered_by');
    $this->assertSession()->linkNotExists('Enable domain configuration');

    // Flip the checkbox via the SettingsForm.
    $this->drupalGet('/admin/config/domain/config-entity-ui');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->checkboxNotChecked('edit-covered-entity-types-block');
    $this->submitForm(['covered_entity_types[block]' => 'block'], 'Save configuration');
    $this->assertSession()->checkboxChecked('edit-covered-entity-types-block');

    // Coverage is now active → toggle appears on the block edit form.
    $this->drupalGet('/admin/structure/block/manage/test_powered_by');
    $this->assertSession()->linkExists('Enable domain configuration');
  }

  /**
   * Tests that no toggle appears on entity types not covered by the swap.
   *
   * The form_alter gates on the entity type's storage handler being
   * an instance of DomainAwareConfigEntityStorageInterface. user_role
   * uses RoleStorage which we have not curated yet, so the role edit
   * form must NOT carry the toggle — exposing it there would let a
   * user register a config the read-side cannot safely round-trip.
   * Once a DomainAwareRoleStorage subclass and the matching
   * entityTypeAlter map entry ship, this test should be updated to
   * the positive assertion alongside one for any other newly-covered
   * type.
   */
  public function testNoToggleOnNonCoveredEntityType(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer permissions',
      'administer domain config ui',
      'administer domains',
      'use domain config ui',
      'view domain information',
    ]));
    // Edit a default role; user_role is not in STORAGE_SWAPS.
    $this->drupalGet('/admin/people/roles/manage/authenticated');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists('Enable domain configuration');
  }

  /**
   * Tests that saving a registered block writes to the per-domain layer.
   *
   * Proving the toggle renders is not enough: the user-reported symptom
   * on #3587744 is that saves silently land on base config. This test
   * exercises the full round-trip — place a block, activate the toggle,
   * edit the label, save — and asserts the per-domain override holds
   * the new value while the base config stays at its original value.
   * Re-rendering the form shows the override-merged value, which is
   * what the higher-priority ParamConverter is responsible for.
   */
  public function testBlockSavePersistsToPerDomainOverride(): void {
    // The end-to-end save here depends on the diff bridge added in
    // https://www.drupal.org/project/domain/issues/3587744 (parent
    // project, domain_config). Older releases of domain_config don't
    // declare the $baseData property on DomainConfigOverrideEditable
    // and would drop the user-submitted label, writing an empty
    // override row — same root cause as the kernel companion
    // testBlockListSaveFlowKeepsLabelOverrideAndAddsWeight skips on.
    // CI may resolve an older domain_config that lacks the bridge;
    // skip rather than fail in that case.
    if (!property_exists(DomainConfigOverrideEditable::class, 'baseData')) {
      self::markTestSkipped('Requires the diff bridge from #3587744 in domain_config.');
    }

    Block::create([
      'id' => 'test_powered_by',
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'content',
      'settings' => ['label' => 'Default label', 'label_display' => 'visible'],
    ])->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/block/manage/test_powered_by');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Enable domain configuration');

    // Activate domain registration for this block's config. The link
    // routes to domain_config_ui.inline_action which registers the
    // config name on the active domain and redirects back to the form.
    $this->clickLink('Enable domain configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Remove domain configuration');

    // Edit the label and save.
    $this->submitForm(['settings[label]' => 'Domain label'], 'Save block');
    $this->assertSession()->statusCodeEquals(200);

    // The active domain id is the FIRST test domain — its hostname matches
    // the simpletest base URL, so the negotiator binds to it on every test
    // request, including the form POST that just ran.
    $domain_id = $this->getDomains()[array_key_first($this->getDomains())]->id();
    self::assertNotEmpty($domain_id, 'A domain id is available for the override lookup.');

    // Per-domain override should hold the new label.
    $override_factory = $this->container->get('domain.config_factory_override');
    $override = $override_factory->getOverride($domain_id, 'block.block.test_powered_by');
    self::assertSame('Domain label', $override->get('settings.label'), 'The per-domain override was written with the form-submitted label.');

    // The override must be sparse: only keys whose values actually differ
    // from base get persisted (#3547172 contract — moduleOverrides holds
    // only what is really overridden). Reading directly from the per-domain
    // collection storage bypasses any base-merge that the override service
    // applies on read.
    $domain_storage = $override_factory->getStorage($domain_id);
    $stored = $domain_storage->read('block.block.test_powered_by');
    self::assertIsArray($stored, 'Per-domain override row is written to storage.');
    self::assertSame(
      ['settings' => ['label' => 'Domain label']],
      $stored,
      'Override storage holds only the keys that differ from base, not the full block payload.'
    );

    // Base config must stay at the original label.
    $base = $this->container->get('config.factory')
      ->getEditable('block.block.test_powered_by');
    self::assertSame('Default label', $base->get('settings.label'), 'The base config was not overwritten by the domain-scoped save.');
    self::assertSame('visible', $base->get('settings.label_display'), 'Untouched base keys are preserved.');

    // Re-render the edit form: the input must show the override-merged value
    // (the user's edit) and not the base label. Drupal core's
    // AdminPathConfigEntityConverter loads config entities override-free on
    // admin paths by default; without the higher-priority converter shipped
    // by this submodule this assertion would still see "Default label",
    // which is exactly the regression reported on #3587744.
    $this->drupalGet('/admin/structure/block/manage/test_powered_by');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('settings[label]', 'Domain label');
  }

}
