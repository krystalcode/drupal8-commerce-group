<?php

namespace Drupal\gcommerce_split\Hook;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\gcommerce_split\MachineName\Field\OrderItem as OrderItemField;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Holds methods implementing hooks related to entity deleting.
 */
class EntityDelete {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EntityDelete object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   *
   * We make sure that split items are deleted when their order item is deleted.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item that was deleted.
   */
  public function commerceOrderItemPostDelete(OrderItemInterface $order_item) {
    if (!$order_item->hasField(OrderItemField::SPLIT_ITEMS)) {
      return;
    }

    $split_items = $order_item->get(OrderItemField::SPLIT_ITEMS)
      ->referencedEntities();
    $this->entityTypeManager
      ->getStorage('gcommerce_split_item')
      ->delete($split_items);
  }

}
