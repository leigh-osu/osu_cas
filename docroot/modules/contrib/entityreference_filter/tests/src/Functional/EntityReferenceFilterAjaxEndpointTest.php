<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AJAX endpoint of the entityreference_filter module.
 *
 * @group entityreference_filter
 */
#[RunTestsInSeparateProcesses]
class EntityReferenceFilterAjaxEndpointTest extends EntityReferenceFunctionalTestBase {

  /**
   * Tests that GET requests are rejected with 405.
   */
  public function testGetNotAllowed(): void {
    $this->drupalGet('/entityreference_filter/ajax');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests that an invalid form id returns 400.
   */
  public function testInvalidFormIdReturnsBadRequest(): void {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    /** @var \Symfony\Component\BrowserKit\AbstractBrowser $client */
    $client = $this->getSession()->getDriver()->getClient();
    $client->request('POST', $this->buildUrl('/entityreference_filter/ajax'), [
      'form_id' => 'invalid<form>id',
      'view' => [
        'view_name' => 'test_view',
        'view_display_id' => 'page_4',
        'view_args' => '',
        'view_context_args' => [],
      ],
      'dependent_filters_data' => ['some_filter' => []],
    ]);
    $this->assertSession()->statusCodeEquals(400);
  }

  /**
   * Tests that a valid POST rebuilds filter options and returns 200.
   */
  public function testValidPostRebuildsOptions(): void {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $controlling = 'field_taxonomy_reference_target_id_entityreference_filter';

    /** @var \Symfony\Component\BrowserKit\AbstractBrowser $client */
    $client = $this->getSession()->getDriver()->getClient();
    $client->request('POST', $this->buildUrl('/entityreference_filter/ajax'), [
      'form_id' => 'views-exposed-form-test-view-page-4',
      'view' => [
        'view_name' => 'test_view',
        'view_display_id' => 'page_4',
        'view_args' => '',
        'view_context_args' => [],
        'view_path' => '/test-view-arg-exposed-filter',
        'view_base_path' => 'test-view-arg-exposed-filter',
        'view_dom_id' => 'dom-id-1',
        'ajax_path' => '/entityreference_filter/ajax',
      ],
      $controlling => 'All',
      'changed_filter' => $controlling,
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $body = $this->getSession()->getPage()->getContent();
    $json = json_decode($body, TRUE);
    $this->assertIsArray($json);
    // The response must contain at least one AJAX command.
    $this->assertNotEmpty($json);
  }

}
