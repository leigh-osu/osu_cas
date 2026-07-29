<?php

declare(strict_types=1);

namespace Drupal\preview_link\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides specific access control for the node entity type.
 *
 * This selection plugin can be changed by altering EntityReferenceSelection
 * manager definitions or by altering base field definitions.
 *
 * @EntityReferenceSelection(
 *   id = "preview_link",
 *   label = @Translation("Preview Link Default"),
 *   group = "preview_link",
 *   weight = 0,
 *   deriver = "Drupal\Core\Entity\Plugin\Derivative\DefaultSelectionDeriver"
 * )
 */
final class PreviewLinkSelection extends DefaultSelection {

}
