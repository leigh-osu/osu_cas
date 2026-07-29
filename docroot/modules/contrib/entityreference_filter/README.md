# Views Reference Filter

This module provides the views filter for entity ID or entity reference fields:

- node ID
- user ID
- taxonomy term ID
- term reference field (Drupal core)
- entity reference field (Entity reference module)

For a full description of the module, visit the
[project page](https://www.drupal.org/project/entityreference_filter).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/entityreference_filter).

## Exposed filter functionality

### Taxonomy term filtering

Imagine the following case - you have taxonomy terms in exposed filter widget
(drop down select list). But for some reason you want to show just several
terms, not all the dictionary. With this module, you can do this by creating a
separate view which generates the desired list of terms.

The similar functionality is available for other type of fields - you create a
separate view (or a new display of the same view) which returns the list of
items to show in drop down select list of the exposed filter.

## Unexposed filter functionality

This module provides the feature missing in Views module - subquery filter for
`IN (NOT IN)` condition, such as (quite informal):

`SELECT n.title FROM node n WHERE n.nid [IN | NOT IN] (SELECT n2.nid FROM node
n2 WHERE ...)`

or

`SELECT n.title FROM node n WHERE n.node_reference_field [IN | NOT IN]
(SELECT n2.nid FROM node n2 WHERE ...)`

The view with the filter generates the outer query, and the separate view
generates the inner query. Current implementation is simplified - it gets the
result of the subquery and puts the concrete values in the outer query.

## Limitations of the view and the display providing the items for the filter

- the view display must be 'Entity Reference' type (providing by Entity
reference module)
- the base entity of the view (which you select in the beginning of view
creation) must be the same as the type of the field being filtered (node for nid
field, user for uid field, etc.)

By default, the view receives the same arguments as the view being filtered.
This can be changed in the filter settings.

Possible value for any argument:

- the argument of the view being filtered
- any string value
- the value of any exposed filter of the view being filtered

The last option makes the exposed filter dependent on the other filter. This
creates the functionality missing in the Views: dynamic dependent filters, i.e.
when the value of the main filter is changed, the list of options of the
dependent filter is updated via AJAX.

## Requirements

This module requires no modules outside of Drupal core (Views).


## Installation

Install as you would normally install a contributed Drupal module.
For further information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Enable the module at Administration > Extend.
1. TODO.


## Maintainers

- Maxim Podorov - [maximpodorov](https://www.drupal.org/u/maximpodorov)
- Maxim Kashuba - [maximkashuba](https://www.drupal.org/u/maximkashuba)
