<?php

namespace Drupal\Tests\views_rss\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the rss fields style display plugin.
 *
 * @group views_rss
 */
class DisplayFeedTest extends EntityKernelTestBase {

  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }
  use NodeCreationTrait {
    getNodeByTitle as drupalGetNodeByTitle;
    createNode as drupalCreateNode;
  }
  use TaxonomyTestTrait {
    createTerm as drupalCreateTerm;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'node',
    'taxonomy',
    'views',
    'views_rss',
    'views_rss_core',
    'views_rss_test_config',
  ];

  /**
   * Test start timestamp used for time comparisons.
   *
   * @var int
   */
  protected $testStartTime;

  /**
   * A file object for testing with.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $image;

  /**
   * The kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig('filter');
    $this->installConfig('node');
    $this->installConfig('views_rss');
    $this->installConfig('views_rss_test_config');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('node', 'node_access');

    $this->testStartTime = \Drupal::time()->getCurrentTime();

    // Create a demo content type called "page".
    $this->drupalCreateContentType(['type' => 'page']);
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_image',
      'bundle' => 'page',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_tags',
      'bundle' => 'page',
    ])->save();

    // Create a file object to use in nodes, etc.
    \Drupal::service('file_system')->copy($this->root . '/core/misc/druplicon.png', 'public://example.jpg');
    $this->image = File::create([
      'uri' => 'public://example.jpg',
    ]);
    $this->image->save();
    $this->httpKernel = $this->container->get('http_kernel');
    $this->user = $this->drupalCreateUser(['access content']);
    $this->drupalSetCurrentUser($this->user);
  }

  /**
   * Tests the rendered output.
   *
   * @todo Rework so that it starts with zero items and then as each node is
   * added the feed changes.
   */
  public function testFeedOutput(): void {
    // Create a demo node of type "page" for use in the feed.
    $node_title = 'This "cool" & "neat" article\'s title';
    $vocabulary = Vocabulary::load('tags');
    $tag = $this->drupalCreateTerm($vocabulary, ['name' => 'Weather']);
    $node = $this->drupalCreateNode([
      'title' => $node_title,
      'body' => [
        0 => [
          'value' => 'A paragraph',
          'format' => filter_default_format(),
        ],
      ],
      'field_image' => $this->image,
      'field_tags' => $tag,
    ]);
    $node_link = $node->toUrl()->setAbsolute()->toString();

    // Create a demo node of type "page" for use in the feed.
    $node2 = $this->drupalCreateNode();
    $node2->setCreatedTime(strtotime(('-1 day')))->save();

    $response = $this->httpKernel->handle(Request::create('views-rss.xml'));
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('application/rss+xml; charset=utf-8', $response->headers->get('Content-Type'));
    libxml_use_internal_errors(TRUE);
    $xml = simplexml_load_string($response->getContent());
    $this->assertEmpty(libxml_get_errors());
    $this->assertEquals($node_title, $xml->channel->item[0]->title);
    $this->assertEquals($node_link, $xml->channel->item[0]->link);
    $this->assertEquals($node_link, $xml->channel->item[0]->comments);
    $this->assertEquals('Weather', (string) $xml->channel->item[0]->category);
    // Verify HTML is properly escaped in the description field.
    $this->assertStringContainsString('&lt;p&gt;A paragraph&lt;/p&gt;', (string) $xml->channel->item[0]->description);

    $enclosure = $xml->channel->item[0]->enclosure;
    $this->assertEquals(\Drupal::service('file_url_generator')->generateAbsoluteString('public://example.jpg'), $enclosure['url']);
    $this->assertEquals($this->image->getSize(), (int) $enclosure['length']);
    $this->assertEquals($this->image->getMimeType(), $enclosure['type']);

    // Verify query parameters are included in the output.
    $response = $this->httpKernel->handle(Request::create('views-rss.xml?field_tags_target_id=1'));
    $xml = simplexml_load_string($response->getContent());
    $this->assertStringContainsString('views-rss.xml?field_tags_target_id=1', (string) $xml->channel->item[0]->source['url']);

    // Verify the channel pubDate matches the highest node pubDate.
    $this->assertEquals(date(\DateTimeInterface::RFC822, $node->getCreatedTime()), $xml->channel->pubDate);
    $this->assertGreaterThanOrEqual($this->testStartTime, strtotime($xml->channel->lastBuildDate));
  }

  /**
   * Test the channel options.
   *
   * @todo Consider moving this into views_rss_core.
   */
  public function testChannelOutput(): void {
    $front_page = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

    // Create a demo node of type "page" for use in the feed.
    $node_title = 'This "cool" & "neat" article\'s title';
    $node = $this->drupalCreateNode([
      'title' => $node_title,
      'body' => [
        0 => [
          'value' => 'A paragraph',
          'format' => filter_default_format(),
        ],
      ],
      'field_image' => $this->image,
    ]);
    // In case that you want to use node link declare variable below $node_link.
    $node->toUrl()->setAbsolute()->toString();
    // Verify the channel has one item of each possible tag.
    $response = $this->httpKernel->handle(Request::create('views-rss.xml'));
    $this->assertEquals(200, $response->getStatusCode());

    libxml_use_internal_errors(TRUE);
    $rss = simplexml_load_string($response->getContent());
    $this->assertEmpty(libxml_get_errors());
    // Verify the basic structure.
    // In order to select the root element you have to specify "any element at
    // the root", which grabs the first "rss" element.
    $this->assertEquals(1, $rss->count());
    $this->assertEquals(1, $rss->channel->count());
    $this->assertEquals(1, $rss->channel->item->count());

    // @todo The title is empty by default, but is present.
    $this->assertEquals(1, $rss->channel->title->count());

    // Expected values from the included view before anything is modified.
    $this->assertEquals(1, $rss->channel->description->count());
    $this->assertEquals(1, $rss->channel->language->count());
    $this->assertEquals(1, $rss->channel->category->count());
    $this->assertEquals(1, $rss->channel->image->count());
    $this->assertEquals(1, $rss->channel->copyright->count());
    $this->assertEquals(1, $rss->channel->managingEditor->count());
    $this->assertEquals(1, $rss->channel->webMaster->count());
    $this->assertEquals(1, $rss->channel->generator->count());
    $this->assertEquals(1, $rss->channel->docs->count());
    $this->assertEquals(1, $rss->channel->cloud->count());
    $this->assertEquals(1, $rss->channel->ttl->count());
    // @todo Properly handle these.
    $this->assertEquals(0, $rss->channel->skipDays->count());
    $this->assertEquals(0, $rss->channel->skipHours->count());

    // Check the default output from the included view.
    $this->assertEquals('Test feed', $rss->channel->title);
    $this->assertEquals('Test description', $rss->channel->description);
    $this->assertEquals($front_page, $rss->channel->link);
    $this->assertEquals('en', $rss->channel->language);
    $this->assertEquals('Test category', $rss->channel->category);
    $this->assertEquals('Test copyright', $rss->channel->copyright);
    $this->assertEquals('Test managingEditor', $rss->channel->managingEditor);
    $this->assertEquals('Test webMaster', $rss->channel->webMaster);
    $this->assertEquals('Test generator', $rss->channel->generator);
    $this->assertEquals('https://www.example.com/something.html', $rss->channel->docs);
    // @todo How about this one?
    // <cloud domain="www.example.com" path="/viewsrsscloud.html"
    // protocol="https"/>\n
    // $this->assertEquals('', $driver->getText('//rss/channel/cloud'));
    $this->assertEquals(600, (int) $rss->channel->ttl);

    // Test the channel image URL. This also confirms the absolute URL handling.
    // @code $site_image_url = $this->getAbsoluteUrl('misc/druplicon.png'); @endcode
    // @code $this->assertEquals($site_image_url, $driver->getText('//rss/channel/image/url')); @endcode
    $this->assertEquals('https://www.drupal.org/misc/druplicon.png', $rss->channel->image->url);
    $this->assertEquals('Test feed', $rss->channel->image->title);
    $this->assertEquals($front_page, $rss->channel->image->link);
    // @todo Work out a better approach for this.
    // $this->assertEquals('', $driver->getText('//rss/channel/image/width'));
    // $this->assertEquals('', $driver->getText('//rss/channel/image/height'));
    //
    // Change the channel description.
    $description = 'Test channel description';
    $config = $this->config('views.view.test_views_rss_feed');
    $config->set('display.feed_1.display_options.style.options.channel.core.views_rss_core.description', $description);
    $config->save();
    drupal_flush_all_caches();

    // Verify the channel description was changed as expected.
    $response = $this->httpKernel->handle(Request::create('views-rss.xml'));
    $this->assertEquals(200, $response->getStatusCode());
    $rss = simplexml_load_string($response->getContent());
    $this->assertEquals($description, $rss->channel->description);

    // Verify that the channel description uses the site slogan when the
    // description is empty.
    $slogan = 'Our awesome site!';
    \Drupal::configFactory()
      ->getEditable('system.site')
      ->set('langcode', 'en')
      ->set('slogan', $slogan)
      ->save(TRUE);
    $config->set('display.feed_1.display_options.style.options.channel.core.views_rss_core.description', '');
    $config->save();
    drupal_flush_all_caches();
    $response = $this->httpKernel->handle(Request::create('views-rss.xml'));
    $this->assertEquals(200, $response->getStatusCode());
    $rss = simplexml_load_string($response->getContent());
    $this->assertEquals($slogan, (string) $rss->channel->description);
  }

}
