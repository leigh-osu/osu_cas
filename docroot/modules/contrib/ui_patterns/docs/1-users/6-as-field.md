# Storing sources in a field

The `ui_patterns_field` sub-module provides the **Source (UI Patterns)** field type. Everywhere else in UI Patterns, a source configuration belongs to a display (block, layout, field formatter, views) and is stored as configuration. This field type stores the same source configuration as **content**, on an entity.

The field is not limited to a single component: each field item stores one source (a source ID and its configuration), and because a source configuration can nest other sources in its slots, a single item can hold a whole component tree. A multi-value field stores several trees, one per item.

There are two ways to use the field:

## On a content entity, edited by users

Attach the field to any fieldable entity (node, block content, paragraph...). Two widgets are available:

- **All slot sources (UI Patterns)**: pick and configure any slot source.
- **Components only (UI Patterns)**: restricted to the component source.

The stored value can then be used in two ways:

- **Render it directly** with the **Render source (UI Patterns)** field formatter.
- **Feed a component configured in a display**: for each entity type with such a field, a source plugin (**Value from the component in field**) is derived. When configuring a component in a display (block, layout, formatter, views), any prop or slot can be mapped to the value configured inside the component stored in the field item. The display defines the composition; the field items provide per-entity values.

## As a storage backend for other modules

The field type is also a persistence layer for modules building on UI Patterns. For example, [Display Builder](https://www.drupal.org/project/display_builder) stores the component tree of each display it manages in a multi-value `ui_patterns_source` field on its own content entity: one root source per item, revisionable and translatable.

## Translating the field

When the field is translatable, the **Synchronized Translation** field setting selects how translations behave:

- **Enabled** (default): every language shares the same structure. Only the text values (labels, texts) are translated per language. Structural changes — adding, removing, moving or reordering a component or a slot item — made on any translation are applied to all languages.
- **Disabled**: each translation stores an independent value. Languages are free to diverge, both in structure and in values.

Keep the setting enabled when translators should only translate texts, and disable it when each language needs its own composition.
