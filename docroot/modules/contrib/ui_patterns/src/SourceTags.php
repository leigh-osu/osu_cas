<?php

declare(strict_types=1);

namespace Drupal\ui_patterns;

/**
 * Tag attributes for source plugins.
 */
enum SourceTags: string {

  /*
   * A source not retrieving data, giving access to other data sources.
   *
   * For example, the author fields from an article content.
   */
  case ContextSwitcher = 'context_switcher';

  // Used by some context switchers.
  case EntityReferenced = 'entity_referenced';

  // Used by some context switchers.
  case Field = 'field';

  // A source which is storing its values instead of pulling data from Drupal.
  case Widget = 'widget';

  // A widget ignored by the conversion mechanism of SourcePluginManager.
  case WidgetDismissible = 'widget:dismissible';

  /*
   * Added by SourcePluginManager.
   *
   * There is also a dynamic `prop_type_matched:{prop_type_id}` tag built by the
   * SourcePluginManager.
   *
   * It seems none of those tags have a proper use.
   */
  case PropTypeNative = 'prop_type_compatibility:native';
  case PropTypeConverted = 'prop_type_compatibility:converted';

}
