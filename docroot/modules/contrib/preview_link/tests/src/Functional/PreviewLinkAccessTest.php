<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\Entity\PreviewLinkInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test access to preview pages with valid/invalid tokens.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
final class PreviewLinkAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'preview_link',
    'preview_link_test',
  ];

  /**
   * Test access with tokens.
   */
  public function testPreviewFakeToken(): void {
    $account = $this->createUser([
      'view test entity',
    ]);
    $this->drupalLogin($account);

    $entity = EntityTestRev::create();
    $entity->save();
    $access = $entity->access('view', $account, TRUE);
    // Make sure the current user has access to the entity.
    $this->assertTrue($access->isAllowed());

    // The entity needs a preview link otherwise the access checker quits early.
    $this->getNewPreviewLinkForEntity($entity);

    // Create a temporary preview link entity to utilize whichever token
    // generation process is in use.
    $token = PreviewLink::create()->getToken();
    // Make sure the token is set.
    $this->assertIsString($token);
    $this->assertTrue(strlen($token) > 0);

    $url = Url::fromRoute('entity.entity_test_rev.preview_link', [
      'entity_test_rev' => $entity->id(),
      'preview_token' => $token,
    ]);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Ensure access is allowed with a real token.
   */
  public function testPreviewRealToken(): void {
    $account = $this->createUser([
      'view test entity',
    ]);
    $this->drupalLogin($account);

    $entity = EntityTestRev::create();
    $entity->save();
    $access = $entity->access('view', $account, TRUE);
    // Make sure the current user has access to the entity.
    $this->assertTrue($access->isAllowed());

    // Create a temporary preview link entity to utilize whichever token
    // generation process is in use.
    $preview = $this->getNewPreviewLinkForEntity($entity);
    $token = $preview->getToken();

    $url = Url::fromRoute('entity.entity_test_rev.preview_link', [
      'entity_test_rev' => $entity->id(),
      'preview_token' => $token,
    ]);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test the preview link routes based on the settings.
   */
  public function testPreviewLinkEnabledEntityTypesConfiguration(): void {
    $config = $this->config('preview_link.settings');

    $account = $this->createUser([
      'view test entity',
    ]);
    $this->drupalLogin($account);

    $entity = EntityTestRev::create();
    $entity->save();

    $preview = $this->getNewPreviewLinkForEntity($entity);
    $token = $preview->getToken();

    $url = Url::fromRoute('entity.entity_test_rev.preview_link', [
      'entity_test_rev' => $entity->id(),
      'preview_token' => $token,
    ]);

    // Allowed when entity types are empty.
    $config->set('enabled_entity_types', [])->save();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    // Forbidden if restricted by entity type.
    $config->set('enabled_entity_types', [
      'foo' => [],
    ])->save();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Allowed if entity type is in restricted list.
    $config->set('enabled_entity_types', [
      'foo' => [],
      'entity_test_rev' => [],
    ])->save();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    // Forbidden if bundle is specific and isn't present.
    $config->set('enabled_entity_types', [
      'foo' => [],
      'entity_test_rev' => [
        'foo',
      ],
    ])->save();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Allowed if bundle is specified and present.
    $config->set('enabled_entity_types', [
      'foo' => [],
      'entity_test_rev' => [
        'foo',
        'entity_test_rev',
      ],
    ])->save();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests access for a referenced entity on a preview link route.
   */
  public function testPreviewLinkReferencedEntity(): void {
    // Set up an entity reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'entity_test_rev_ref',
      'entity_type' => 'entity_test_rev',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'entity_test_rev',
      ],
    ]);
    $field_storage->save();
    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test_rev',
      'label' => $this->randomMachineName(),
    ]);
    $instance->save();

    // Set up the field display.
    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test_rev',
      'bundle' => 'entity_test_rev',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('entity_test_rev_ref', [
      // Render the entity in full to trigger the "view" operation since
      // EntityTestAccessControlHandler has $viewLabelOperation set to TRUE.
      'type' => 'entity_reference_entity_view',
    ])->save();

    // Create test content.
    $reference = EntityTestRev::create();
    $reference->save();
    $referee = EntityTestRev::create([
      'entity_test_rev_ref' => $reference,
    ]);
    $referee->save();

    $account = $this->createUser([
      'view test entity',
    ]);
    $this->drupalLogin($account);

    $preview = $this->getNewPreviewLinkForEntity($referee);
    $token = $preview->getToken();

    // Check the referenced entity shows on the preview page.
    $url = Url::fromRoute('entity.entity_test_rev.preview_link', [
      'entity_test_rev' => $referee->id(),
      'preview_token' => $token,
    ]);
    $this->drupalGet($url);
    $label = (string) $reference->label();
    $this->assertSession()->pageTextContains($label);

    // Check it still shows the referenced entity when it has a preview link
    // as well.
    $this->getNewPreviewLinkForEntity($reference);
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains($label);
  }

  /**
   * Get a saved preview link for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A content entity.
   *
   * @return \Drupal\preview_link\Entity\PreviewLinkInterface|null
   *   The preview link, or null if no preview link generated.
   */
  protected function getNewPreviewLinkForEntity(ContentEntityInterface $entity): ?PreviewLinkInterface {
    $previewLink = PreviewLink::create()->addEntity($entity);
    $previewLink->save();
    return $previewLink;
  }

}
