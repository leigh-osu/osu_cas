# Charts Highcharts Maps

This module uses Highcharts Maps to create maps for the Charts module.
Mainly choropleth maps where the color intensity relates to some value
of a geographic area. You need to have either charts_highcharts 
(bundled with charts) or charts_highstock (separate contrib module) 
enabled for it to work.

## Installation using Composer (recommended)

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
               "name": "highcharts/maps",
               "version": "11.1.0",
               "type": "drupal-library",
               "extra": {
                   "installer-name": "highcharts_maps"
               },
               "dist": {
                   "url": "https://code.highcharts.com/maps/11.1.0/modules/map.js",
                   "type": "file"
               },
               "require": {
                   "composer/installers": "^1.0 || ^2.0"
               }
           }
       }

4. Run `composer require --prefer-dist highcharts/maps:11.1.0` - you should
find that new directories have been created under "/libraries"

5. Add a long text field to the desired geographic locations e.g. countries /
districts / regions that will store their respective TopoJSON or GeoJSON

6. Add text field to store the HASC code of the 'admin1' level geographic
locations
