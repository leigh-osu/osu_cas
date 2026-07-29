<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\views\display;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\views\Plugin\views\display\Block
 * @group block
 */
class BlockTest extends UnitTestCase {

  /**
   * The view executable.
   *
   * @var \Drupal\views\ViewExecutable|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $executable;

  /**
   * The views block plugin.
   *
   * @var \Drupal\views\Plugin\Block\ViewsBlock|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $blockPlugin;

  /**
   * The tested block display plugin.
   *
   * @var \Drupal\views\Plugin\views\display\Block|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $blockDisplay;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $methods = [
      'id',
      'executeDisplay',
      'setDisplay',
      'setItemsPerPage',
      'getRequest',
    ];
    $this->executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->onlyMethods($methods)
      ->getMock();
    $this->executable->expects($this->any())
      ->method('setDisplay')
      ->with('block_1')
      ->willReturn(TRUE);
    $this->executable->expects($this->any())
      ->method('id')
      ->will($this->returnValue('foo'));
    $this->executable->expects($this->any())
      ->method('getRequest')
      ->will($this->returnValue(new Request()));

    $key_value = $this->getMockBuilder('Drupal\Core\KeyValueStore\DatabaseStorage')
      ->disableOriginalConstructor()
      ->onlyMethods(['has', 'set'])
      ->getMock();
    $key_value->expects($this->any())
      ->method('has')
      ->will($this->returnValue(TRUE));
    $key_value->expects($this->any())
      ->method('set')
      ->will($this->returnValue(NULL));
    $key_value_factory = $this->getMockBuilder('Drupal\Core\KeyValueStore\KeyValueDatabaseFactory')
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();
    $key_value_factory->expects($this->any())
      ->method('get')
      ->will($this->returnValue($key_value));
    $args = [
      [],
      'views_block',
      [],
      $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManagerInterface')
        ->disableOriginalConstructor()
        ->getMock(),
      $this->getMockBuilder('Drupal\Core\Block\BlockManagerInterface')
        ->disableOriginalConstructor()
        ->getMock(),
      $key_value_factory,
      $this->getMockBuilder('Drupal\Core\Plugin\Context\ContextRepositoryInterface')
        ->disableOriginalConstructor()
        ->getMock(),
      $this->getMockBuilder('Drupal\Core\Plugin\Context\ContextHandlerInterface')
        ->disableOriginalConstructor()
        ->getMock(),
    ];
    $this->blockDisplay = $this->executable->display_handler = $this->getMockBuilder('Drupal\views\Plugin\views\display\Block')
      ->setConstructorArgs($args)
      ->onlyMethods(['calculateConfigurationHash'])
      ->getMock();
    $this->blockDisplay->expects($this->any())
      ->method('calculateConfigurationHash')
      ->will($this->returnValue('foobar'));

    $this->blockDisplay->view = $this->executable;

    $this->blockPlugin = $this->getMockBuilder('Drupal\views\Plugin\Block\ViewsBlock')
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Tests the build method with no overriding.
   */
  public function testBuildNoOverride(): void {
    $this->executable->expects($this->never())
      ->method('setItemsPerPage');

    $this->blockPlugin->expects($this->once())
      ->method('getConfiguration')
      ->willReturn(['items_per_page' => 'none']);

    $this->blockDisplay->preBlockBuild($this->blockPlugin);
  }

  /**
   * Tests the build method with overriding items per page.
   */
  public function testBuildOverride(): void {
    $this->executable->expects($this->once())
      ->method('setItemsPerPage')
      ->with(5);

    $this->blockPlugin->expects($this->once())
      ->method('getConfiguration')
      ->willReturn(['items_per_page' => 5]);

    $this->blockDisplay->preBlockBuild($this->blockPlugin);
  }

}
