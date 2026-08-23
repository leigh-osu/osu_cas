## INTRODUCTION
Adds some current-page:url token variations that are geared for customized canonical urls that contain query parameters.

## Features

Spurred into being by [#1198032: The [current-page:url] token should include the query string](https://www.drupal.org/project/token/issues/1198032), this module provides the tokens:

`[current-page:url-with-query]`

The URL of the current page with query string included, if it exists.

`[current-page:url-with-query:without-some-parameters:param1,param2,…]`

The URL of the current page with query string included but filtered by removing the provided query parameters. e.g. 'without-some-parameters:utm_campaign,utm_medium,utm_source' would be replaced with the current url, with the three utm parameters filtered out if they existed.

`[current-page:url-with-query:with-some-parameters:param1,param2,…]`

The URL of the current page with query string included but filtered to only include the provided query parameters. e.g. 'with-some-parameters:page,category_id' would return a url containing only query parameters, '?page=1&category_id=2' if they existed.

## REQUIREMENTS

[Tokens](https://www.drupal.org/project/token)

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.


## MAINTAINERS

Current maintainers for Drupal 10:

- Allan Chappell (generalredneck) - https://www.drupal.org/u/generalredneck

