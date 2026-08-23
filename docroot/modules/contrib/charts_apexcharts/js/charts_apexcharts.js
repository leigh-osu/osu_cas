/**
 * @file
 * JavaScript integration between Apexcharts.js and Drupal.
 */
/* global ApexCharts */
(function (Drupal, once, ApexCharts) {
  Drupal.behaviors.chartsApexcharts = {
    attach(context) {
      once('charts-apexcharts-chart', '.charts-apexcharts', context).forEach(
        function (element) {
          const contents = new Drupal.Charts.Contents();
          const id = element.id;
          const config = contents.getData(id);
          if (!config) {
            return;
          }

          config.chart.renderTo = id;
          const chart = new ApexCharts(element, config);
          chart.render();

          if (
            element.nextElementSibling &&
            element.nextElementSibling.hasAttribute(
              'data-charts-debug-container',
            )
          ) {
            element.nextElementSibling.querySelector('code').innerText =
              JSON.stringify(config, null, ' ');
          }
        },
      );
    },
  };
})(Drupal, once, ApexCharts);
