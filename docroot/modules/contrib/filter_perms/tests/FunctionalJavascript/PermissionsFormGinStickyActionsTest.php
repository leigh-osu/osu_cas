<?php

namespace Drupal\Tests\filter_perms\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests saving permissions via Gin's real sticky form actions JavaScript.
 *
 * Replicates https://www.drupal.org/project/filter_perms/issues/3609578 using
 * the actual Gin theme and its more_actions.js, rather than a hand-crafted
 * HTTP request, since Gin is present in this codebase.
 */
#[Group('filter_perms')]
#[RunTestsInSeparateProcesses]
class PermissionsFormGinStickyActionsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'filter_perms'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('theme_installer')->install(['gin']);
    $this->config('system.theme')->set('admin', 'gin')->save();
    $this->config('gin.settings')->set('sticky_action_buttons', TRUE)->save();
  }

  /**
   * Tests that clicking Gin's sticky Save button actually saves permissions.
   */
  public function testSaveViaGinStickyActionButton() {
    $author_permissions = [
      'access administration pages',
      'view the administration theme',
    ];
    $author_role = $this->drupalCreateRole($author_permissions, 'author_role', 'Author role');
    $admin = $this->drupalCreateUser([], 'admin_test', TRUE);
    $this->drupalLogin($admin);

    $this->drupalGet('admin/people/permissions');
    $assert_session = $this->assertSession();

    $filter_edit = [
      'roles[]' => [$author_role],
      'modules[]' => ['user'],
    ];
    $this->submitForm($filter_edit, 'Filter Permissions');
    $assert_session->waitForElementVisible('css', 'table.permissions');

    $permission_field = $author_role . '[access user profiles]';
    $assert_session->checkboxNotChecked($permission_field);

    $page = $this->getSession()->getPage();
    $page->checkField($permission_field);

    // Confirm Gin actually attached its sticky actions bar and wired up a
    // sticky counterpart for the Save button, so this test would fail loudly
    // (rather than silently no-op) if Gin's markup/JS changes in a way that
    // stops applying to this form.
    $sticky_selector = '.gin-sticky-form-actions [data-drupal-selector^="gin-sticky-edit-submit"]';
    $sticky_save = $assert_session->waitForElementVisible('css', $sticky_selector);
    $this->assertNotNull($sticky_save, 'Gin did not render a sticky counterpart for the Save permissions button.');

    // Click the STICKY Save button, exactly as a real user would when Gin's
    // sticky form actions are enabled, rather than the original in-form
    // button. This is what triggers Gin's more_actions.js click-forwarding
    // logic reported to drop the triggering button's info from the POST.
    // Re-find the element right before clicking to avoid a stale reference
    // if Gin's JS re-attached/rebuilt the sticky bar in the meantime.
    $this->getSession()->getPage()->find('css', $sticky_selector)->click();

    $assert_session->waitForText('The changes have been saved.', 10000);

    $this->drupalGet('admin/people/permissions');
    $this->submitForm($filter_edit, 'Filter Permissions');
    $assert_session->checkboxChecked($permission_field);
  }

}
