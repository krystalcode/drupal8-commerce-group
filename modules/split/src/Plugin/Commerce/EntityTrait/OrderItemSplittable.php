<?php

namespace Drupal\gcommerce_split\Plugin\Commerce\EntityTrait;

use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;
use Drupal\entity\BundleFieldDefinition;
use Drupal\gcommerce_split\MachineName\Field\OrderItem as OrderItemField;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Provides a trait for configuring whether order items of a type can be split.
 *
 * @CommerceEntityTrait(
 *   id = "gcommerce_order_item_splittable",
 *   label = @Translation("Splittable"),
 *   entity_types = {"commerce_order_item"}
 * )
 */
class OrderItemSplittable extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];
    $fields[OrderItemField::SPLIT_ITEMS] = BundleFieldDefinition::create('entity_reference')
      ->setLabel('Split items')
      ->setDescription('The split items for the order item.')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'gcommerce_split_item')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
      ]);

    return $fields;
  }

}
