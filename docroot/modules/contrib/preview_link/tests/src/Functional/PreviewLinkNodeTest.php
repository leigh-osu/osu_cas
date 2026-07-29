<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nodes are referencable.
 *
 * When using the 'default' reference group only published nodes were
 * referencable.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
final class PreviewLinkNodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_entity_reference',
    'preview_link',
    'node',
  ];

  /**
   * Tests referencing an unpublished node.
   */
  public function testReferenceUnpublishedNode(): void {
    $this->createContentType(['type' => 'page']);

    $this->drupalLogin($this->createUser([
      'generate preview links',
      'access content',
    ]));
    $node1 = $this->createNode([
      'title' => 'node1',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $node2 = $this->createNode([
      'title' => 'node2',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    $generateUrl = $node1->toUrl('preview-link-generate');
    $this->drupalGet($generateUrl);

    $this->submitForm(
      [
        'entities[1][target_id]' => 'node2 (' . $node2->id() . ')',
      ],
      'Save',
    );
    // This would fail if unpublished wasnt referencable:
    $this->assertSession()->pageTextContains('Preview Link saved.');
  }

}
