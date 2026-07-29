<?php

namespace Drupal\Tests\entitygroupfield\FunctionalJavascript;

use Drupal\Core\Session\AccountInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\group\PermissionScopeInterface;
use Drupal\Tests\entitygroupfield\Traits\GroupCreationTrait;
use Drupal\Tests\entitygroupfield\Traits\TestGroupsTrait;

/**
 * Tests the JavaScript AJAX functionality of the entitygroupfield widgets.
 *
 * @group entitygroupfield
 */
class EntityGroupFieldWidgetTest extends WebDriverTestBase {

  use GroupCreationTrait;
  use TestGroupsTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'field_ui',
    'group',
    'gnode',
    'entitygroupfield',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Regular authenticated User for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Regular authenticated User for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Configure our field formatter to show group labels (as links).
    foreach (['article', 'page'] as $node_type) {
      \Drupal::service('entity_display.repository')
        ->getViewDisplay('node', $node_type)
        ->setComponent('entitygroupfield', [
          'type' => 'parent_group_label_formatter',
          'settings' => [
            'link' => TRUE,
          ],
          'label' => 'above',
        ])
        ->save();
    }

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'access group overview',
      'administer account settings',
      'administer content types',
      'administer group',
      'administer node fields',
      'administer node display',
      'administer users',
      'bypass node access',
    ]);
    $this->testUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'create page content',
      'edit own article content',
      'edit own page content',
    ]);
  }

  /**
   * Initialize the test.
   *
   * These are things that would otherwise be in self::setUp(), but that we want
   * to do after some initial assertions.
   */
  protected function initializeTest() {
    // Setup the group types and test groups from the TestGroupsTrait.
    $this->initializeTestGroups();

    // Enable article nodes to be assigned to only 'A' group type.
    $this->entityTypeManager->getStorage(entitygroupfield_get_group_relationship_type_id())
      ->createFromPlugin($this->groupTypeA, 'group_node:article')->save();
    // Let page nodes be assigned to both 'A' and 'B' groups.
    $this->entityTypeManager->getStorage(entitygroupfield_get_group_relationship_type_id())
      ->createFromPlugin($this->groupTypeA, 'group_node:page')->save();
    $this->entityTypeManager->getStorage(entitygroupfield_get_group_relationship_type_id())
      ->createFromPlugin($this->groupTypeB, 'group_node:page')->save();

    // Create admin role for group types.
    $this->entityTypeManager->getStorage('group_role')
      ->create([
        'id' => $this->randomMachineName(8),
        'label' => $this->randomString(),
        'group_type' => $this->groupTypeA->id(),
        'scope' => PermissionScopeInterface::OUTSIDER_ID,
        'global_role' => $this->adminUser->getRoles(TRUE)[0],
        'admin' => TRUE,
      ])->save();
    $this->entityTypeManager->getStorage('group_role')
      ->create([
        'id' => $this->randomMachineName(8),
        'label' => $this->randomString(),
        'group_type' => $this->groupTypeB->id(),
        'scope' => PermissionScopeInterface::OUTSIDER_ID,
        'global_role' => $this->adminUser->getRoles(TRUE)[0],
        'admin' => TRUE,
      ])->save();
    // Let regular group members view and add content to the groups.
    $this->entityTypeManager->getStorage('group_role')
      ->create([
        'id' => $this->randomMachineName(8),
        'label' => $this->randomString(),
        'group_type' => $this->groupTypeA->id(),
        'scope' => PermissionScopeInterface::INSIDER_ID,
        'global_role' => AccountInterface::AUTHENTICATED_ROLE,
        'permissions' => [
          'view group',
          'create group_node:article relationship',
          'create group_node:page relationship',
          'delete own group_node:article relationship',
          'delete own group_node:page relationship',
          'update own group_node:article relationship',
          'update own group_node:page relationship',
          'view group_node:article relationship',
          'view group_node:article entity',
          'view group_node:page relationship',
          'view group_node:page entity',
        ],
      ])->save();
    $this->entityTypeManager->getStorage('group_role')
      ->create([
        'id' => $this->randomMachineName(8),
        'label' => $this->randomString(),
        'group_type' => $this->groupTypeB->id(),
        'scope' => PermissionScopeInterface::INSIDER_ID,
        'global_role' => AccountInterface::AUTHENTICATED_ROLE,
        'permissions' => [
          'view group',
          'create group_node:page relationship',
          'delete own group_node:page relationship',
          'update own group_node:page relationship',
          'view group_node:page relationship',
          'view group_node:page entity',
        ],
      ])->save();

    // Subscribe the testUser to groups 1 + 2 (but not 3) in both types (A/B).
    $this->groupA1->addMember($this->testUser);
    $this->groupA2->addMember($this->testUser);
    $this->groupB1->addMember($this->testUser);
    $this->groupB2->addMember($this->testUser);
  }

  /**
   * Test group field widgets.
   */
  public function testFieldWidgets() {
    // Before we configure anything, make sure we don't see our widgets.
    // Verify users.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/people/create');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertEmpty($groups_widget);
    $groups_element = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertEmpty($groups_element);
    $add_group_button = $page->findButton('Add to Group');
    $this->assertEmpty($add_group_button);

    // Verify article nodes.
    $this->drupalGet('/node/add/article');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertEmpty($groups_widget);
    $groups_element = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertEmpty($groups_element);
    $add_group_button = $page->findButton('Add to Group');
    $this->assertEmpty($add_group_button);
    // Verify page nodes.
    $this->drupalGet('/node/add/page');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertEmpty($groups_widget);
    $groups_element = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertEmpty($groups_element);
    $add_group_button = $page->findButton('Add to Group');
    $this->assertEmpty($add_group_button);

    // Note: the rest of this function invokes protected helper methods, instead
    // of defining those as entirely new test* methods, to avoid the (intense)
    // startup costs of FunctionalJavascript tests.
    //
    // Before we create any groups or types, try the select widget on a user.
    $this->checkUserSelectWidgetBeforeGroups();

    // Initialize our test group types and groups.
    $this->initializeTest();

    // Try the select widget on users now that group types and groups exist.
    $this->checkUserSelectWidget();

    // Switch to a non-admin to make sure access control works as expected.
    $this->drupalLogout();
    $this->drupalLogin($this->testUser);

    // Try both of the widgets on each of the node types.
    $this->checkArticleAutocompleteWidget();
    $this->checkArticleSelectWidget();
    $this->checkPageAutocompleteWidget();
    $this->checkPageSelectWidget();
  }

  /**
   * Test the 'select' group field widget on users before any groups exist.
   */
  protected function checkUserSelectWidgetBeforeGroups() {
    // Configure users to use the select widget.
    $custom_label = $this->randomMachineName(10);
    $custom_help_text = $this->randomMachineName(20);
    $this->configureFormDisplay('user', 'user', [
      'type' => 'entitygroupfield_select_widget',
      'settings' => [
        'multiple' => TRUE,
        'required' => FALSE,
        'label' => $custom_label,
        'help_text' => $custom_help_text,
      ],
    ]);

    // Now we should see the widget.
    $this->drupalGet('/admin/people/create');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertNotEmpty($groups_widget);
    $this->assertSession()->pageTextContains('Group memberships');
    $this->assertSession()->pageTextContains('Not yet added to groups.');
    $groups_select = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertEmpty($groups_select);
    $add_group_button = $page->findButton('Add to Group');
    $this->assertEmpty($add_group_button);
  }

  /**
   * Test the 'select' group field widget on users with groups and types.
   */
  protected function checkUserSelectWidget() {
    // Configure users to use the select widget.
    $custom_label = $this->randomMachineName(10);
    $custom_help_text = $this->randomMachineName(20);
    $this->configureFormDisplay('user', 'user', [
      'type' => 'entitygroupfield_select_widget',
      'settings' => [
        'multiple' => TRUE,
        'required' => FALSE,
        'label' => $custom_label,
        'help_text' => $custom_help_text,
      ],
    ]);

    // We should see the widget.
    $this->drupalGet('/admin/people/create');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertNotEmpty($groups_widget);
    $this->assertSession()->pageTextContains('Group memberships');
    $groups_select_name = 'entitygroupfield[add_more][add_relation]';
    $groups_select = $page->findField($groups_select_name);
    $this->assertNotEmpty($groups_select);

    // Ensure our custom label and help text are used.
    $label = $this->xpath('//label[@for=:for and contains(text(), :value)]', [
      ':for' => 'edit-entitygroupfield-add-more-add-relation',
      ':value' => $custom_label,
    ]);
    $this->assertNotEmpty($label);
    $help_text = $this->xpath('//div[@id=:id and contains(text(), :value)]', [
      ':id' => 'edit-entitygroupfield-add-more-add-relation--description',
      ':value' => $custom_help_text,
    ]);
    $this->assertNotEmpty($help_text);

    // Ensure the default option makes sense.
    $default_option = $this->assertSession()->optionExists($groups_select_name, '- Select a group -');
    $this->assertNotEmpty($default_option);
    $this->assertTrue($default_option->hasAttribute('selected'));
    // Since this is a user, all 6 groups should be options.
    $this->assertNotEmpty($groups_select->find('named', ['option', 1]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 2]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 3]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 4]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 5]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 6]));
    // And both opt groups.
    $this->assertNotEmpty($groups_select->find('named', [
      'optgroup', $this->groupTypeA->label(),
    ]));
    $this->assertNotEmpty($groups_select->find('named', [
      'optgroup', $this->groupTypeB->label(),
    ]));

    // Try to add to groupA1.
    $groups_select->setValue($this->groupA1->id());
    $add_group_button = $page->findButton('Add to Group');
    $this->assertNotEmpty($add_group_button);
    $add_group_button->click();
    $groups_table = $this->assertSession()->waitForElementVisible('css', '#edit-entitygroupfield-wrapper table');
    $this->assertNotEmpty($groups_table);
  }

  /**
   * Test the 'autocomplete' group field widget on article nodes.
   */
  protected function checkArticleAutocompleteWidget() {
    // Configure articles to use the autocomplete widget.
    $this->configureFormDisplay('node', 'article', [
      'type' => 'entitygroupfield_autocomplete_widget',
      'settings' => [
        'multiple' => TRUE,
        'required' => FALSE,
      ],
    ]);

    // Now we should see the widget.
    $this->drupalGet('/node/add/article');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertNotEmpty($groups_widget);
    $groups_autocomplete = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertNotEmpty($groups_autocomplete);

    // Actually test the autocomplete.
    $groups_autocomplete->setValue('group');
    $this->getSession()->getDriver()->keyDown($groups_autocomplete->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();

    // Check the autocomplete results.
    $results = $page->findAll('css', '.ui-autocomplete li');
    $this->assertCount(2, $results);
    $this->assertEquals($this->groupA1->label(), $results[0]->getText());
    $this->assertEquals($this->groupA2->label(), $results[1]->getText());

    // Click on the first result and make sure it works.
    $page->find('css', '.ui-autocomplete li:first-child a')->click();
    $this->assertSession()->fieldValueEquals('entitygroupfield[add_more][add_relation]', $this->groupA1->label() . ' (' . $this->groupA1->id() . ')');
    // Press the button to actually add this article to the selected group.
    $page->findButton('Add to Group')->click();
    // Make sure the table loads so we know AJAX worked before we continue.
    $this->assertSession()->waitForElementVisible('css', '.field--name-entitygroupfield table');

    // Fill in the required title field.
    $new_title = $this->randomString();
    $title = $page->findField('title[0][value]');
    $title->setValue($new_title);
    // Save the article.
    $page->findButton('Save')->click();
    // Confirm that saving created the new article.
    $this->assertSession()->waitForText("Article $new_title has been created.");

    // Should see this is in group-A1 now (from our label field formatter).
    $this->assertTrue($page->hasLink($this->groupA1->label()));
    // But not in group-A2.
    $this->assertFalse($page->hasLink($this->groupA2->label()));

    // Ensure there is no access bypass via the autocomplete widget.
    $this->checkArticleAutocompleteWidgetAccessBypass();

    // Let authenticated outsiders view groups, but nothing else.
    $outsider_role = $this->entityTypeManager->getStorage('group_role')
      ->create([
        'id' => $this->randomMachineName(8),
        'label' => $this->randomString(),
        'group_type' => $this->groupTypeA->id(),
        'scope' => PermissionScopeInterface::OUTSIDER_ID,
        'global_role' => AccountInterface::AUTHENTICATED_ROLE,
        'permissions' => [
          'view group',
        ],
      ]);
    $outsider_role->save();

    // Try again to use the autocomplete widget to add a new article to the
    // group this user is not a member of.
    $this->checkArticleAutocompleteWidgetAccessBypass();

    // Now that that check is complete, revoke the permission again so the rest
    // of this test can assume that outsiders cannot view groups.
    $outsider_role->revokePermission('view group');
    $outsider_role->save();
  }

  /**
   * Test possible access bypass in the  'autocomplete' widget on article nodes.
   */
  protected function checkArticleAutocompleteWidgetAccessBypass() {
    $this->drupalGet('/node/add/article');
    $page = $this->getSession()->getPage();
    $new_title = $this->randomString();
    $title = $page->findField('title[0][value]');
    $title->setValue($new_title);
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertNotEmpty($groups_widget);
    $groups_autocomplete = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertNotEmpty($groups_autocomplete);
    // Try to add to a group the user isn't a member of.
    $groups_autocomplete->setValue('group-A3 (3)');
    $page->findButton('Add to Group')->click();
    // @todo There should be an error message printed immediately we could wait
    // for and assert, but for now we have to click 'Save' to see it.
    $page->findButton('Save')->click();
    // Should see an error message about group ID 3.
    $this->assertSession()->waitForText('The referenced entity (group: 3) does not exist.');
    // A new article should not be created.
    $this->assertSession()->pageTextNotContains("Article $new_title has been created.");
    // There shouldn't be a link to group-A3 anywhere.
    $this->assertFalse($page->hasLink($this->groupA3->label()));
  }

  /**
   * Test the 'select' group field widget on article nodes.
   */
  protected function checkArticleSelectWidget() {
    $assert_session = $this->assertSession();

    // Configure articles to use the select widget.
    $custom_label = $this->randomMachineName(10);
    $custom_help_text = $this->randomMachineName(20);
    $this->configureFormDisplay('node', 'article', [
      'type' => 'entitygroupfield_select_widget',
      'settings' => [
        'multiple' => TRUE,
        'required' => FALSE,
        'label' => $custom_label,
        'help_text' => $custom_help_text,
      ],
    ]);

    // Now we should see the widget.
    $this->drupalGet('/node/add/article');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertNotEmpty($groups_widget);
    $groups_select = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertNotEmpty($groups_select);
    // Since this is an article, only 'A' type groups should be options.
    $this->assertNotEmpty($groups_select->find('named', [
      'option', '- Select a group -',
    ]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 1]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 2]));
    $this->assertEmpty($groups_select->find('named', ['option', 3]));
    $this->assertEmpty($groups_select->find('named', ['option', 4]));
    $groups_select->setValue('1');
    $add_group_button = $page->findButton('Add to Group');
    $this->assertNotEmpty($add_group_button);
    $add_group_button->click();
    $groups_table = $assert_session->waitForElementVisible('css', '#edit-entitygroupfield-wrapper table');
    $this->assertNotEmpty($groups_table);
    $group_name = $groups_table->find('css', 'tbody tr td .gcontent-type-title');
    $this->assertNotEmpty($group_name);
    $this->assertSame($this->groupA1->label(), $group_name->getText());

    $groups_select = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertNotEmpty($groups_select);
    // Make sure the group we added is no longer an option in the select list.
    // @todo We'll have to be more careful with this once we correctly handle
    //   group cardinality settings.
    // @see https://www.drupal.org/project/entitygroupfield/issues/3152719
    $this->assertNotEmpty($groups_select->find('named', [
      'option', '- Select a group -',
    ]));
    $this->assertEmpty($groups_select->find('named', ['option', 1]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 2]));
    $this->assertEmpty($groups_select->find('named', ['option', 3]));
    $this->assertEmpty($groups_select->find('named', ['option', 4]));

    // Add the 2nd group.
    $groups_select->setValue('2');
    $add_group_button = $page->findButton('Add to Group');
    $this->assertNotEmpty($add_group_button);
    $add_group_button->click();
    // Wait for a row with 'group-A2' to appear in the 'Groups' table.
    $group_a2_cell = $assert_session->waitForElementVisible('xpath', '//div[@id="edit-entitygroupfield-wrapper"]//table/tbody/tr/td//div[contains(text(), "group-A2")]');
    $this->assertNotEmpty($group_a2_cell);

    // Now that we used both groups, there shouldn't be an add button anymore.
    $groups_select = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertEmpty($groups_select);

    // Test the remove buttons.
    // Remove the first row (group-A1).
    $remove_button = $page->findButton('entitygroupfield_0_remove');
    $this->assertNotEmpty($remove_button);
    $remove_button->press();
    $confirm_button = $assert_session->waitForButton('Confirm removal');
    $this->assertNotEmpty($confirm_button);
    $confirm_button->press();
    $groups_table = $assert_session->waitForElementVisible('css', '#edit-entitygroupfield-wrapper table');
    $this->assertNotEmpty($groups_table);

    // Make sure the row for group-A1 is gone.
    $group_a1_cell = $page->find('xpath', '//div[@id="edit-entitygroupfield-wrapper"]//table/tbody/tr/td//div[contains(text(), "group-A1")]');
    $this->assertEmpty($group_a1_cell);

    // The groups select should be back.
    $groups_select = $assert_session->waitForField('entitygroupfield[add_more][add_relation]');
    $this->assertNotEmpty($groups_select);
    // It should have group-A1 in it.
    $this->assertNotEmpty($groups_select->find('named', ['option', 1]));
    $this->assertEmpty($groups_select->find('named', ['option', 2]));
    $this->assertEmpty($groups_select->find('named', ['option', 3]));
    $this->assertEmpty($groups_select->find('named', ['option', 4]));
  }

  /**
   * Test the 'autocomplete' group field widget on page nodes.
   */
  protected function checkPageAutocompleteWidget() {
    // Configure pages to use the autocomplete widget.
    $this->configureFormDisplay('node', 'page', [
      'type' => 'entitygroupfield_autocomplete_widget',
      'settings' => [
        'multiple' => TRUE,
        'required' => FALSE,
      ],
    ]);

    // Now we should see the widget.
    $this->drupalGet('/node/add/page');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertNotEmpty($groups_widget);
    $groups_autocomplete = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertNotEmpty($groups_autocomplete);

    // Actually test the autocomplete.
    $groups_autocomplete->setValue('group');
    $this->getSession()->getDriver()->keyDown($groups_autocomplete->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();

    // Check the autocomplete results. Should see the 4 groups the testUer is a
    // member of.
    $results = $page->findAll('css', '.ui-autocomplete li');
    $this->assertCount(4, $results);
    $this->assertEquals($this->groupA1->label(), $results[0]->getText());
    $this->assertEquals($this->groupA2->label(), $results[1]->getText());
    $this->assertEquals($this->groupB1->label(), $results[2]->getText());
    $this->assertEquals($this->groupB2->label(), $results[3]->getText());

    // Click on the last result and make sure it works.
    $page->find('css', '.ui-autocomplete li:last-child a')->click();
    $this->assertSession()->fieldValueEquals('entitygroupfield[add_more][add_relation]', $this->groupB2->label() . ' (' . $this->groupB2->id() . ')');
  }

  /**
   * Test the 'select' group field widget on page nodes.
   */
  protected function checkPageSelectWidget() {
    $this->assertSession();

    // Configure pages to use the select widget.
    $custom_label = $this->randomMachineName(10);
    $custom_help_text = $this->randomMachineName(20);
    $this->configureFormDisplay('node', 'page', [
      'type' => 'entitygroupfield_select_widget',
      'settings' => [
        'multiple' => TRUE,
        'required' => FALSE,
        'label' => $custom_label,
        'help_text' => $custom_help_text,
      ],
    ]);
    $this->drupalGet('/node/add/page');
    $page = $this->getSession()->getPage();
    $groups_widget = $page->findAll('css', '#edit-entitygroupfield');
    $this->assertNotEmpty($groups_widget);
    $groups_select = $page->findField('entitygroupfield[add_more][add_relation]');
    $this->assertNotEmpty($groups_select);
    // As a page node, all 4 groups the user is in should be options.
    $this->assertNotEmpty($groups_select->find('named', [
      'option',
      '- Select a group -',
    ]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 1]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 2]));
    // User not a member of group-A3.
    $this->assertNotEmpty($groups_select->find('named', ['option', 4]));
    $this->assertNotEmpty($groups_select->find('named', ['option', 5]));
    // And both opt groups.
    $this->assertNotEmpty($groups_select->find('named', [
      'optgroup',
      $this->groupTypeA->label(),
    ]));
    $this->assertNotEmpty($groups_select->find('named', [
      'optgroup',
      $this->groupTypeB->label(),
    ]));

    // @todo Anything else we should test with both A and B groups that we
    // didn't already cover with articles?
  }

  /**
   * Configures the form display mode for the 'entitygroupfield' field.
   *
   * @param string $entity_type
   *   The entity type to configure.
   * @param string $bundle
   *   (Optional) The entity bundle to configure.
   * @param array $config
   *   The configuration array to use for the 'entitygroupfield' field.
   */
  protected function configureFormDisplay($entity_type, $bundle, array $config) {
    \Drupal::service('entity_display.repository')
      ->getFormDisplay($entity_type, $bundle)
      ->setComponent('entitygroupfield', $config)
      ->save();
  }

}
