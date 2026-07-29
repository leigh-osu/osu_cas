<?php

declare(strict_types=1);

namespace Drupal\node_revision_delete\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a node revision delete plugin attribute object.
 *
 * @see \Drupal\node_revision_delete\Plugin\NodeRevisionDeletePluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class NodeRevisionDelete extends Plugin {

  /**
   * Constructs a NodeRevisionDelete attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the label.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {}

}
