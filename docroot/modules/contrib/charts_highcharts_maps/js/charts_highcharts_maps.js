/**
 * @file
 * JavaScript's integration between Highmap and Drupal.
 */

(function (Drupal, once, drupalSettings) {

  'use strict';

  async function fetchMapData(mapDataSourceSettings) {
    let url = '';
    if (mapDataSourceSettings.json_file) {
      url = mapDataSourceSettings.json_file;
    }
    else if (mapDataSourceSettings.tid && mapDataSourceSettings.json_field_name) {
      url = `${drupalSettings.path.baseUrl}${drupalSettings.path.pathPrefix}charts-highmap/map-data/${mapDataSourceSettings.json_field_name}/${mapDataSourceSettings.tid}`;
    }

    if (!url) {
      return {};
    }

    const response = await fetch(url).catch(e => console.error(e));
    return response.json();
  }

  Drupal.theme.chartsHighchartsMapsAnnotationPopup = (name, content, options) =>
    `
    <div id="${options.annotationPopupId}--header" class="chats-highcharts-maps--annotation-popup--header">
      <span>${name}</span>
      <button id="${options.annotationPopupCloseBtnId}">
        <span>X</span>
      </button>
    </div>
    <div id="${options.annotationPopupId}--content" class="chats-highcharts-maps--annotation-popup--content">
      ${content}
    </div>
    `;

  Drupal.behaviors.chartsHighmap = {
    attach: function (context) {
      const contents = new Drupal.Charts.Contents();
      once('charts-highmap', '.charts-highmap', context).forEach(async function (element) {
        const id = element.id;
        const config = contents.getData(id);
        if (!config) {
          return;
        }

        config.chart.renderTo = id;
        const mapData = await fetchMapData(config.mapDataSourceSettings);
        if (mapData) {
          config.chart.map = mapData;
        }
        if (element.dataset.useAnnotations) {
          config.series[0].point = {
            events: {
              click: pointClick
            }
          };
        }
        new Highcharts.mapChart(config);

        if (element.nextElementSibling && element.nextElementSibling.hasAttribute('data-charts-debug-container')) {
          element.nextElementSibling.querySelector('code').innerText = JSON.stringify(config, null, ' ');
        }
      });
    },
    detach: function (context, settings, trigger) {
      if (trigger === 'unload') {
        once('charts-highmap-detach', '.charts-highmap', context).forEach(function (element) {
          if (!element.dataset.hasOwnProperty('highchartsChart')) {
            return;
          }
          Highcharts.charts[element.dataset.highchartsChart].destroy();
        });
      }
    },
  };

  function pointClick() {
    const annotationId = this.id;
    const chart = this.series.chart;
    // Get the annotation where the annotation_id matches the point.
    // Here is the format of the annotation:
    // chart.annotations.X.labels.X.points.X.id where X is a number.
    const annotation = chart.annotations.find(annotation => annotation.labels.find(label => label.points.find(point => point.id === annotationId)));
    const annotationText = annotation.userOptions.labels[0].text;

    const annotationPopupId = `${chart.userOptions.drupalChartDivId}--annotation-popup`;
    const annotationPopupCloseBtnId = `${annotationPopupId}--close-btn`;

    // Remove existing annotation if present.
    chart.removeAnnotation(annotationPopupId);

    chart.addAnnotation({
      id: annotationPopupId,
      labelOptions: {
        useHTML: true,
        backgroundColor: '#fff'
      },
      labels: [{
        point: {
          x: chart.plotWidth / 2,
          y: chart.plotHeight / 10
        },
        text: Drupal.theme('chartsHighchartsMapsAnnotationPopup', this.name, annotationText, {
          annotationPopupId,
          annotationPopupCloseBtnId,
        }),
        shape: 'rect'
      }],
      zIndex: 10
    });

    document.getElementById(annotationPopupCloseBtnId)
      .addEventListener('click', (e) => {
        e.preventDefault();
        setTimeout( () => chart.removeAnnotation(annotationPopupId), 0);
      });
  }
}(Drupal, once, drupalSettings));
