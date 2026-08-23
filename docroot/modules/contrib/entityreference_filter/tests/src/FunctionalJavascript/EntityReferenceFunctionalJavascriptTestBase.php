<?php

declare(strict_types=1);

namespace Drupal\Tests\entityreference_filter\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\entityreference_filter\Traits\ContentSetupTrait;
use Drupal\Tests\entityreference_filter\Traits\EntityReferenceFilterTrait;

/**
 * Tests entityreference filter behavior.
 *
 * @group entityreference_filter
 */
abstract class EntityReferenceFunctionalJavascriptTestBase extends WebDriverTestBase {

  use ContentSetupTrait;
  use EntityReferenceFilterTrait;

  protected const NODE_TYPE_ARTICLE = 'article';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'views',
    'entityreference_filter',
    'entityreference_filter_test_config',
  ];

  /**
   * Views to import.
   *
   * @var array
   */
  public $viewsToCreate = [
    'test_view',
    'test_entityreference_view_terms',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->contentPrepare();
    $this->createTestViews($this->viewsToCreate);
  }

}
