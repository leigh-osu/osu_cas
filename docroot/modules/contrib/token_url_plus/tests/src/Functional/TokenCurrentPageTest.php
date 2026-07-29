<?php

namespace Drupal\Tests\token_url_plus\Functional;

use Drupal\Tests\token\Functional\TokenTestBase;

/**
 * Test the [current-page:url-with-query:*] tokens.
 *
 * @group token
 */
class TokenCurrentPageTest extends TokenTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'token_url_plus'];

  /**
   * Test current-page:url-with-query tokens.
   */
  public function testCurrentPageTokens() {
    // Cache clear is necessary because the frontpage was already cached by an
    // initial request.
    $this->drupalCreateContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['title' => 'Node title', 'path' => ['alias' => '/node-alias']]);
    $this->rebuildAll();
    $tokens = [
      '[current-page:url-with-query]' => htmlentities($node->toUrl(
        'canonical',
        ['query' => ['foo' => 'bar', 'bar' => 'baz', 'page' => '2'], 'absolute' => TRUE]
        )->toString()),
      '[current-page:url-with-query:with-some-parameters:foo]' => htmlentities($node->toUrl(
        'canonical',
        ['query' => ['foo' => 'bar'], 'absolute' => TRUE]
        )->toString()),
      '[current-page:url-with-query:with-some-parameters:foo,bar]' => htmlentities($node->toUrl(
        'canonical',
        ['query' => ['foo' => 'bar', 'bar' => 'baz'], 'absolute' => TRUE]
        )->toString()),
      '[current-page:url-with-query:without-some-parameters:foo]' => htmlentities($node->toUrl(
        'canonical',
        ['query' => ['bar' => 'baz', 'page' => '2'], 'absolute' => TRUE]
        )->toString()),
      '[current-page:url-with-query:without-some-parameters:foo,bar]' => htmlentities($node->toUrl(
        'canonical', ['query' => ['page' => '2'], 'absolute' => TRUE]
        )->toString()),
    ];
    $this->assertPageTokens(
      "/node/{$node->id()}",
      $tokens,
      [],
      [
        'url_options' => [
          'query' => [
            'foo' => 'bar',
            'bar' => 'baz',
            'page' => '2',
          ],
        ],
      ]
    );
  }

}
