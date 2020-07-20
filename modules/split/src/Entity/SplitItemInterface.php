<?php

namespace Drupal\gcommerce_split\Entity;

use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Defines the interface for split items.
 *
 * Split items define how an order item is split between multiple customers. For
 * example, an order item that references a purchased entity (e.g. product
 * variation) with quantity 10 lbs might be split between three customers: a
 * customer purchasing 3 lbs, a customer purchasing 5 lbs and a customer
 * purchasing 2 lbs.
 *
 * Each split item holds the customer, the quantity and the price.
 */
interface SplitItemInterface extends
  ContentEntityInterface,
  EntityChangedInterface,
  EntityOwnerInterface {

  /**
   * Gets the parent order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   The order item.
   */
  public function getOrderItem();

  /**
   * Gets the parent order item ID.
   *
   * @return int
   *   The order item ID.
   */
  public function getOrderItemId();

  /**
   * Gets the split item's quantity.
   *
   * @return string
   *   The split item's quantity
   */
  public function getQuantity();

  /**
   * Sets the split item's quantity.
   *
   * @param string $quantity
   *   The split item's quantity.
   *
   * @return $this
   */
  public function setQuantity($quantity);

  /**
   * Gets the split item's price.
   *
   * @return \Drupal\commerce_price\Price
   *   The order item's price.
   */
  public function getPrice();

  /**
   * Gets the split item's creation timestamp.
   *
   * @return int
   *   The split item's creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the split item's creation timestamp.
   *
   * @param int $timestamp
   *   The split item's creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

}
