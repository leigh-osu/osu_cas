# Charts Highcharts Drilldown

This module creates a Views style plugin that extends the [Charts](https://www.drupal.org/project/charts) module's 
style plugin to enable the [Highcharts drilldown](https://www.highcharts.com/docs/chart-concepts/drilldown) feature.


For a full description of the module, visit the
[project page](https://www.drupal.org/project/charts_highcharts_drilldown).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/charts_highcharts_drilldown).

## Table of contents

- Dependencies
- Requirements
- Installation
- Configuration
- Maintainers

## Dependencies

* [Highcharts drilldown library](https://www.highcharts.com/docs/chart-concepts/drilldown) (>=11.1.0)

## Requirements

This module requires the following module:

- [Charts](https://www.drupal.org/project/charts)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

### Installation using Composer (recommended)

If you use Composer to manage dependencies, edit your site's "composer.json"
file as follows.

1. Run `composer require --prefer-dist composer/installers` to ensure that
   you have the "composer/installers" package installed. This package
   facilitates the installation of packages into directories other than
   "/vendor" (e.g. "/libraries") using Composer.

2. Add the following to the "installer-paths" section of "composer.json":

        "libraries/{$name}": ["type:drupal-library"],

3. Add the following to the "repositories" section of "composer.json":

        {
            "type": "package",
            "package": {
                "name": "highcharts/drilldown",
                "version": "11.1.0",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_drilldown"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/drilldown.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        }

4. Run `composer require --prefer-dist highcharts/drilldown:12.1.1` 
   you should find that new directories have been
   created under "/libraries"

## Configuration

The module has no menu or modifiable settings. There is no configuration. When
enabled, the module will add a new style plugin (Chart highcharts drilldown) inside the site Views.
The style option form come with four more fields to set up your drilldown chart.

* Series Field: This is the parent series and the data is aggregated at this level first.
* Drilldown Field: This field provide the drilldown series when you click down the chart.
* Data Field: The data field is the values field.
* Operator Field: Here you can choose either to sum or average the data.

## Maintainers

- Mamadou Diao Diallo - [diaodiallo](https://www.drupal.org/u/diaodiallo)
- Daniel Cothran - [andileco](https://www.drupal.org/u/andileco)
