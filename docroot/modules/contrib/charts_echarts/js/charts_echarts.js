/**
 * @file
 * JavaScript integration between EChart.js and Drupal.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.chartsECharts = {
    attach(context) {

      once('charts-echarts-init', 'body', context).forEach(function () {
        const contents = new Drupal.Charts.Contents();
        const charts = [];
        const elements = once('charts-echarts-chart', '.charts-echarts', context);

        elements.forEach(function (element) {
          const chart = contents.getData(element.id);
          const options = chart['options'] || {};

          // Adjust the height and width of the chart based on the grid settings.
          // This is to that parts of the chart are not cut off.
          // I would love to find a better way to do this.
          options.height = options.height || 400;
          options.height_units = options.height_units || 'px';
          options.width = options.width || 400;
          options.width_units = options.width_units || 'px';
          const grid = options.grid || {};
          const gridTop = parseInt(grid.top) || 0;
          const gridBottom = parseInt(grid.bottom) || 0;
          const gridLeft = parseInt(grid.left) || 0;
          const gridRight = parseInt(grid.right) || 0;
          const gridHeightAdjustment = isNaN(gridTop) || isNaN(gridBottom) ? 0 : gridTop + gridBottom;
          const gridWidthAdjustment = isNaN(gridLeft) || isNaN(gridRight) ? 0 : gridLeft + gridRight;
          options.height = options.height - gridHeightAdjustment;
          options.width = options.width - gridWidthAdjustment;
          element.style.height = `${options.height + gridHeightAdjustment}${options.height_units}`;

          const myChart = echarts.init(element, null, { renderer: 'svg' });

          if (options && typeof options === 'object') {
            myChart.setOption(options);
          }

          if (element.nextElementSibling && element.nextElementSibling.hasAttribute('data-charts-debug-container')) {
            element.nextElementSibling.querySelector('code').innerText = JSON.stringify(chart, null, 2);
          }

          charts.push(myChart);
        });

        window.onresize = function () {
          charts.forEach(chart => chart.resize());
        };
      });
    }
  };
}(Drupal, once));
