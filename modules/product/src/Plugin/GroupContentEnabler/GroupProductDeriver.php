<?php

namespace Drupal\gcommerce_product\Plugin\GroupContentEnabler;

use Drupal\commerce_product\Entity\ProductType;
use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Provides per product bundle definitions of the group content enabler plugin.
 *
 * @I Use dependency injection for loading product types and translation
 *    type     : task
 *    priority : normal
 *    labels   : coding-standards, dependency-injection, product
 */
class GroupProductDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach (ProductType::loadMultiple() as $name => $product_type) {
      $label = $product_type->label();

      $this->derivatives[$name] = [
        'entity_bundle' => $name,
        'label' => t('Group product (@type)', ['@type' => $label]),
        'description' => t(
          'Adds %type products to groups both publicly and privately.',
          ['%type' => $label]
        ),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
