<?php

declare(strict_types=1);

namespace Drupal\preview_link;

use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

/**
 * Interface for Preview Link entities.
 *
 * @method \Drupal\preview_link\Entity\PreviewLinkInterface[] loadMultiple(array $ids = NULL)
 * @method \Drupal\preview_link\Entity\PreviewLinkInterface create(array $values = [])
 * @method int save(\Drupal\preview_link\Entity\PreviewLinkInterface $entity)
 */
interface PreviewLinkStorageInterface extends SqlEntityStorageInterface {

}
