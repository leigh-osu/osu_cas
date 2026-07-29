# Charts ApexCharts

This is a module that integrates the [ApexCharts](https://apexcharts.com/)
library with the [Charts](https://www.drupal.org/project/charts) module.

"ApexCharts is a modern charting library that helps developers to create
beautiful and interactive visualizations for web pages." (ApexCharts website).

The project is open-source (MIT license) and free to use in commercial
applications.

Available chart types and features include:
- [Area](https://apexcharts.com/javascript-chart-demos/area-charts/),
- [Range Area](https://apexcharts.com/javascript-chart-demos/range-area-charts/),
- [Bar](https://apexcharts.com/javascript-chart-demos/bar-charts/),
- [Box & Whisker](https://apexcharts.com/javascript-chart-demos/box-whisker-charts/),
- [Bubble](https://apexcharts.com/javascript-chart-demos/bubble-charts/),
- [Candlestick](https://apexcharts.com/javascript-chart-demos/candlestick-charts/),
- [Column](https://apexcharts.com/javascript-chart-demos/column-charts/),
- [Donut](https://apexcharts.com/javascript-chart-demos/pie-charts/simple-donut/),
- [Dumbbell](https://apexcharts.com/javascript-chart-demos/timeline-charts/dumbbell-chart-horizontal/),
- [Gauge/Radial Bar](https://apexcharts.com/javascript-chart-demos/radialbar-charts/semi-circle-gauge/),
- [Heatmap](https://apexcharts.com/javascript-chart-demos/heatmap-charts/),
- [Line](https://apexcharts.com/javascript-chart-demos/line-charts/),
- [Pie](https://apexcharts.com/javascript-chart-demos/pie-charts/),
- [Radar](https://apexcharts.com/javascript-chart-demos/radar-charts/),
- [Range-Bars](https://apexcharts.com/javascript-chart-demos/timeline-charts/),
- [Scatter](https://apexcharts.com/javascript-chart-demos/scatter-charts/),
- [Slope](https://apexcharts.com/javascript-chart-demos/slope-charts/),
- [Spline](https://apexcharts.com/javascript-chart-demos/area-charts/spline/),
- [Treemap](https://apexcharts.com/javascript-chart-demos/treemap-charts/),

The Charts module allows for combination charts either through Views or 
the Charts API.

## Table of contents

- Requirements
- Recommended modules
- Installation
- Configuration
- Maintainers

## Requirements

This module requires the following modules:

- [Charts](https://www.drupal.org/project/charts) (version 5.2.1 or higher)

## Recommended modules

If you don't want to use data already in your site, here are a few
recommended modules:

- [Charts AI Agents](https://www.drupal.org/project/charts_ai_agents)
- [Views CSV Source](https://www.drupal.org/project/views_csv_source)
- [Views JSON Source](https://www.drupal.org/project/views_json_source)
- [Views Database Connector](https://www.drupal.org/project/views_database_connector)
- [External Entities](https://www.drupal.org/project/external_entities)
- [Views Fields On/Off](https://www.drupal.org/project/views_fields_on_off)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

This module uses [Asset Packagist](https://asset-packagist.org/) to bring in
the ApexCharts library. Before you require the module with Composer, you need
to make sure the following exist in your composer.json file:

1. The Asset Packagist repository is added to the "repositories" section.
   ```
   "repositories": [
     {
       "type": "composer",
        "url": "https://packages.drupal.org/8"
      },
      {
        "type": "composer",
        "url": "https://asset-packagist.org"
       }
   ],
   ```

2. The "oomphinc/composer-installers-extender" package is installed.
   This package allows you to install libraries into the "/libraries" directory
   instead of the default "/vendor" directory.
   ```
   composer require --prefer-dist oomphinc/composer-installers-extender
   ```

3. The installer types and paths are set in the "extra" section of your
   composer.json file. This tells Composer to install ApexCharts into the
   "/libraries/apexcharts" directory.
   ```
   "extra": {
     "installer-types": ["npm-asset", "bower-asset"],
     "installer-paths": {
       "web/libraries/{$name}": [
         "type:drupal-library",
         "type:bower-asset",
         "type:npm-asset"
       ]
     }
   }
   ```

## Configuration

1. Enable the module at Administration > Extend.
2. Set ApexCharts as your default charting library
   at /admin/config/content/charts.
3. Create your chart! There are three ways to create a chart:
   - Create a view and use the "Chart" format
   - Add a chart field to your entity type (e.g. node, user, etc.)
   - Use the Charts API to create a chart programmatically

## Maintainers

- Daniel Cothran - [andileco](https://www.drupal.org/u/andileco)
