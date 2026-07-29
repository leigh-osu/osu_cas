<?php

declare(strict_types=1);

namespace Drupal\Tests\preview_link\Functional;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestRevPub;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Integration test for the preview link and layout builder.
 */
#[Group('preview_link')]
#[RunTestsInSeparateProcesses]
class PreviewLinkLayoutBuilderTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'preview_link_test',
    'preview_link',
    'node',
    'layout_builder',
  ];

  /**
   * Test there is no preview link redirection on layout builder pages.
   */
  public function testNoRedirectOnLayoutPage(): void {
    $user = $this->createUser([
      'generate preview links',
      'access content',
      'configure any layout',
    ]);
    $this->createContentType(['type' => 'foo']);
    $entity = $this->drupalCreateNode([
      'type' => 'foo',
      'status' => 1,
    ]);

    // Enable layout builder overrides.
    LayoutBuilderEntityViewDisplay::load('node.foo.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    \Drupal::configFactory()
      ->getEditable('preview_link.settings')
      ->set('enabled_entity_types', [
        'node' => ['foo'],
      ])
      ->save();
    $this->drupalLogin($user);
    $this->drupalGet($entity->toUrl('preview-link-generate'));
    $link = $this->cssSelect('.preview-link__link')[0]->getText();
    $this->drupalGet($link);

    $this->drupalGet(Url::fromRoute('layout_builder.overrides.node.view', [
      'node' => $entity->id(),
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals(sprintf('node/%s/layout', $entity->id()));
  }

}
