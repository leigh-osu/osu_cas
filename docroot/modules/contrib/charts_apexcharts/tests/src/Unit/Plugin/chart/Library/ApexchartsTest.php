<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_apexcharts\Unit\Plugin\chart\Library;

use Drupal\charts_apexcharts\Plugin\chart\Library\Apexcharts;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ChartsConfig Form class.
 *
 * @group charts
 * @coversDefaultClass \Drupal\charts_apexcharts\Plugin\chart\Library\Apexcharts
 * @use \Drupal\charts\Plugin\chart\Library\ChartBase
 */
class ApexchartsTest extends UnitTestCase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $elementInfo;

  /**
   * The plugin manager for chart types.
   *
   * @var \Drupal\charts\Plugin\chart\Type\TypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $pluginManagerChartsType;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $formBuilder;

  /**
   * The Apexcharts plugin.
   *
   * @var \Drupal\charts_apexcharts\Plugin\chart\Library\Apexcharts
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $container->set('module_handler', $this->moduleHandler);

    $this->elementInfo = $this->createMock('Drupal\Core\Render\ElementInfoManagerInterface');
    $container->set('element_info', $this->elementInfo);

    $this->pluginManagerChartsType = $this->createMock('Drupal\charts\TypeManager');
    $container->set('plugin.manager.charts_type', $this->pluginManagerChartsType);

    $this->formBuilder = $this->createMock('Drupal\Core\Form\FormBuilderInterface');
    $container->set('form_builder', $this->formBuilder);

    \Drupal::setContainer($container);

    $this->plugin = Apexcharts::create($container, [], 'apexcharts', [
      'id' => 'apexcharts',
      'label' => 'Apexcharts',
      'provider' => 'charts_apexcharts',
    ]);

  }

  /**
   * Tests the creation of the Apexcharts plugin.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $plugin = Apexcharts::create($container, [], 'apexcharts', [
      'id' => 'apexcharts',
      'label' => 'Apexcharts',
      'provider' => 'charts_apexcharts',
    ]);
    $this->assertInstanceOf(Apexcharts::class, $plugin);
  }

  /**
   * Tests the constructor of the Apexcharts plugin.
   *
   * @covers ::__construct
   */
  public function testConstruct(): void {
    $plugin = new Apexcharts(
      [],
      'apexcharts',
      [
        'id' => 'apexcharts',
        'label' => 'Apexcharts',
        'provider' => 'charts_apexcharts',
      ],
      $this->elementInfo,
      $this->pluginManagerChartsType,
      $this->formBuilder,
      $this->moduleHandler,
    );

    $this->assertInstanceOf(Apexcharts::class, $plugin);
  }

}
