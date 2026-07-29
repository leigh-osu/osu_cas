
CONTENTS OF THIS FILE
---------------------

 * Overview
 * Setup
 * Search as Block
 * Customization
 * Maintainers

OVERVIEW
--------

Google Programmable Search is an embedded search engine that can be used to
search any set of one or more sites. No Google API key is required. Read more
at https://developers.google.com/custom-search.

SETUP
-----

1. Before installing this module, register a Google Search engine at
https://programmablesearchengine.google.com/cse/all.
2. Install this module and create a search instance at 
admin/config/search/pages. At a minimum, configuration must include the search
page path and Google Search ID (which you created in Step 1). 
3. Optionally set this as the default Drupal search.
4. Grant the "View Google Programmable Search" permission to one or more roles
to use Google Search.

If you set this search instance as the default Drupal search, the core search
block will redirect directly to your site's Google search results page. 

If you instead want to embed the search form and its results within a page, use
the Google Programmable Search block, described below.

SEARCH AS BLOCK
---------------

For sites that do not want search results to display on a standalone page, this
module includes a Google Programmable Search block which can be enabled at
admin/structure/block. This block provides a combined search box and with
search results. After entering search terms, the user will be returned to the
same page and the results will be displayed. **Important**: Do not configure
this block to appear on the search page, as the search results will fail to
display.

CUSTOMIZATION
-------------

You can use optional attributes to overwrite configurations created in the
Programmable Search Engine control panel (for example, you can set autocomplete
behavior, toggle image search, restrict results by country or language, and set
the ordering and number of results). These attributes can be added in the
Drupal search configuration form under "Customizations." This module does not
document these attributes; rather, it is the responsibility of the site
maintainer to understand and correctly use the available attributes listed at
https://developers.google.com/custom-search/docs/element.

MAINTAINERS
-----------

For bugs, feature requests and support requests, please use the issue queue
 at http://drupal.org/project/issues/google_cse

The Drupal 8 version of this module is maintained by the following 
organizations:

 * QED42 - https://www.drupal.org/qed42

  QED42 is a web development agency focussed on helping organisations and
  individuals reach their potential, most of our work is in the space of
  publishing, e-commerce, social and enterprise.

 * University of Texas at Austin - https://www.drupal.org/university-of-texas-at-austin

 The University of Texas at Austin (UT Austin) is the flagship campus of the 
 University of Texas System. It is one of the largest public universities in
 the United States and a leader in groundbreaking research and higher education
 innovation. What starts here changes the world.
