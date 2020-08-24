<?php

namespace Drupal\gcommerce_split\Hook;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\gcommerce_split\MachineName\Field\OrderItem as OrderItemField;

/**
 * Holds methods implementing hooks related to entity saving.
 */
class EntitySave {

  /**
   * Implements hook_ENTITY_TYPE_insert() and hook_ENTITY_TYPE_update().
   *
   * Whenever a new or existing order item is saved, we make sure that its split
   * items have a back-reference to it as well.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item that was saved.
   */
  public function commerceOrderItemPostSave(OrderItemInterface $order_item) {
    if (!$order_item->hasField(OrderItemField::SPLIT_ITEMS)) {
      return;
    }

    $split_items = $order_item->get(OrderItemField::SPLIT_ITEMS)
      ->referencedEntities();
    foreach ($split_items as $split_item) {
      if ($split_item->get('order_item_id')->target_id == $order_item->id()) {
        continue;
      }

      $split_item->set('order_item_id', $order_item->id());
      $split_item->save();
    }
  }

}
