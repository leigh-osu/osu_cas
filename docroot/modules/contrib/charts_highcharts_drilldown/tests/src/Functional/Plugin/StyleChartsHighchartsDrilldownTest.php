<?php

namespace Drupal\Tests\charts_highcharts_drilldown\Functional\Plugin;

use Drupal\Component\Utility\Html;
use Drupal\Tests\views\Functional\ViewTestBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the chart_highcharts_drilldown style views plugin.
 *
 * @group charts_highcharts_drilldown
 */
class StyleChartsHighchartsDrilldownTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_charts_highcharts_drilldown'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'charts',
    'charts_highcharts',
    'charts_highcharts_drilldown',
    'charts_highcharts_drilldown_test',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['charts_highcharts_drilldown_test']): void {
    parent::setUp($import_test_views, $modules);

    $this->enableViewsTestModule();
  }

  /**
   * Tests the generated JSON generated from the views.
   */
  public function testGeneratedJson() {
    $this->drupalGet('test-charts-highcharts-drilldown');

    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    $attribute = 'data-chart';
    $display_id = 'page_1';
    $view_id_selector = Html::getId('chart-' . static::$testViews[0] . '-' . $display_id);
    $chart_container = $this->assertSession()
      ->elementAttributeExists('css', "#{$view_id_selector}", $attribute);

    $actual = str_replace('&quot;', '"', (string) $chart_container->getAttribute($attribute));
    $expected = '{"chart":{"type":"column","backgroundColor":"","polar":0,"options3d":{"enabled":0}},"credits":{"enabled":false},"title":{"text":"Test charts highcharts drilldown","style":{"color":"#000"}},"subtitle":{"text":""},"colors":["#006fb0","#f07c33","#342e9c","#579b17","#3f067a","#cbde67","#7643b6","#738d00","#c157c7","#02dab1","#ed56b4","#d8d981","#004695","#736000","#a5a5ff","#833a00","#ff9ee9","#684507","#fe4f85","#5d0011","#ffa67b","#88005c","#ff9b8f","#85000f","#ff7581"],"tooltip":{"enabled":true,"useHTML":false,"headerFormat":"\u003Cspan style=\u0022font-size: 0.9em\u0022\u003E{series.name}\u003C\/span\u003E\u003Cbr\u003E","pointFormat":"\u003Cspan style=\u0022color:{point.color}\u0022\u003E\u25cf\u003C\/span\u003E \u003Cspan\u003E{point.name}\u003C\/span\u003E: \u003Cb\u003E{point.y}\u003C\/b\u003E"},"plotOptions":{"series":{"stacking":false,"dataLabels":{"enabled":true},"marker":{"enabled":true},"connectNulls":false}},"legend":{"enabled":true,"verticalAlign":"bottom","layout":"horizontal","symbolPadding":0,"symbolWidth":0,"symbolHeight":0,"squareSymbol":false},"drilldown":{"series":[{"name":"Below 30","id":"below-30","data":[["Singer",25],["Singer",27],["Drummer",28],["Songwriter",26]]},{"name":"Over 30","id":"over-30","data":[["Speaker",30]]}]},"xAxis":{"type":"category"},"yAxis":{"title":{"text":"Downloads"}},"series":[{"name":"Age type","data":[{"name":"Below 30","y":106,"drilldown":"below-30","color":"#006fb0"},{"name":"Over 30","y":30,"drilldown":"over-30","color":"#f07c33"}]}],"accessibility":{"announceNewData":{"enabled":true}}}';
    $this->assertEquals($expected, $actual);
  }

}
