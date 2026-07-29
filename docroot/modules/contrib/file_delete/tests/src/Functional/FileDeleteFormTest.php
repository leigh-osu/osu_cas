<?php

declare(strict_types=1);

namespace Drupal\Tests\file_delete\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * End-to-end coverage of the FileDeleteForm via the actual UI.
 *
 * @group file_delete
 */
class FileDeleteFormTest extends BrowserTestBase {

  use TestFileCreationTrait;
  use ImageFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file_delete',
    'node',
    'image',
    'field',
    'file',
    'user',
    'views',
  ];

  /**
   * Tests the bug-report flow: force-delete + instant-delete on an in-use file.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testForceDeleteKeepsReferencingEntity(): void {
    $this->drupalCreateContentType(['type' => 'article']);
    $this->createImageField('field_image', 'node', 'article');

    $admin = $this->drupalCreateUser([
      'access content',
      'delete any file',
      'delete files override usage',
      'delete files immediately',
    ]);
    $this->drupalLogin($admin);

    $test_image = current($this->getTestFiles('image'));
    /** @var \Drupal\file\FileInterface $file */
    $file = File::create([
      'uri' => $test_image->uri,
      'filename' => 'test_file_delete.png',
      'status' => 1,
    ]);
    $file->save();
    $file_id = (int) $file->id();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Test node',
      'field_image' => [['target_id' => $file_id]],
    ]);
    $node->save();
    $node_id = (int) $node->id();

    /** @var \Drupal\file\FileUsage\FileUsageInterface $usage */
    $usage = $this->container->get('file.usage');
    $listed = $usage->listUsage($file);
    $this->assertArrayHasKey('file', $listed);
    $this->assertArrayHasKey('node', $listed['file']);
    $this->assertArrayHasKey($node_id, $listed['file']['node']);

    $this->drupalGet($file->toUrl('delete-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'force_delete' => 1,
      'instant_delete' => 1,
    ], 'Delete File');

    // The bug-version submit handler triggers a node delete which adds an
    // error message via the form's try/catch. Assert no such error appears.
    $this->assertSession()->pageTextNotContains('cannot be deleted');

    $this->assertNotNull(Node::load($node_id));

    $this->assertNull(File::load($file_id));
    $remaining = $usage->listUsage($file);
    $this->assertEmpty($remaining);
  }

}
