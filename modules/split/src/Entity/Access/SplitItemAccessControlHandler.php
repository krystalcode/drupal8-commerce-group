<?php

namespace Drupal\gcommerce_split\Entity\Access;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\gcommerce_split\Entity\SplitItemInterface;
use Drupal\entity\EntityAccessControlHandler;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the `SplitItem` entity.
 *
 * The split item permissions are related to the order that the associated
 * order item belong to.
 *
 * If the order is a cart order, the following apply to the cart pages:
 * - If the user has only `view` access to the cart, they can only have access
 *   to view the split items for the order's items. Giving them permission to
 *   update or delete the split items will not have any effect in that case.
 * - If the user has `update` access to the cart, they can have access to update
 *   or delete split items for the order's items.
 *
 * The following access control applies whether the order is a cart or not (for
 * example, in the UI for store managers):
 * - If the user has only `view` access to the order, they can only have access
 *   to view the split items for the order's items. Giving them permission to
 *   update or delete the split items will not have any effect in that case.
 * - If the user has `update` access to the order, they can have access to
 *   update or delete split items for the order's items.
 *
 * The split item-related permissions are required i.e. a user that has `update`
 * access to an order will not be able to update the order's split items unless
 * given the relevant split item permission.
 */
class SplitItemAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(
    EntityInterface $split_item,
    $operation,
    AccountInterface $account
  ) {
    $account = $this->prepareUser($account);

    // If the user does not have the right split item permissions, we don't need
    // to check for order permissions. The result also does not depend on the
    // order so we don't need to add that in the cacheable dependencies.
    $result = parent::checkAccess($split_item, $operation, $account, TRUE);
    if (!$result->isAllowed()) {
      return $result->addCacheableDependency($split_item);
    }

    // The only operations relevant to cart orders are `view`, `update` and
    // `delete`. If there's any other custom operations defined by another
    // module we don't know if there are relevant to carts and if they depend to
    // corresponding order permissions; we therefore cannot be sure how to
    // handle them. They would need to be handled by the provider.
    if (!in_array($operation, ['view', 'update', 'delete'])) {
      return $result->addCacheableDependency($split_item);
    }

    $order = $split_item->getOrderItem()->getOrder();

    // If the user has access based on order permissions, they are given access
    // regardless of whether the order is a cart.
    $order_access = $this->hasOrderAccess($order, $operation, $account);
    if ($order_access) {
      return $this->buildAccessResult($result, $split_item, $order, TRUE);
    }

    // Otherwise, they may be given access based on cart permissions.
    if ($order->get('cart')->value) {
      return $this->buildAccessResult(
        $result,
        $split_item,
        $order,
        $this->hasCartAccess($order, $operation, $account)
      );
    }

    return $this->buildAccessResult(
      $result,
      $split_item,
      $order,
      FALSE
    );
  }

  /**
   * Returns whether the user has access to the order for the given operation.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $operation
   *   The operation to check access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   Whether the user has access to the order or not.
   */
  protected function hasOrderAccess(
    OrderInterface $order,
    $operation,
    AccountInterface $account
  ) {
    // Users are allowed to create, update or delete split items if they have
    // permissions to update the order.
    if (in_array($operation, ['create', 'delete'])) {
      $operation = 'update';
    }

    return $order->access($operation, $account);
  }

  /**
   * Returns whether the user has access to the cart for the given operation.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The cart order.
   * @param string $operation
   *   The operation to check access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   Whether the user has access to the cart order or not.
   *
   * @throws \InvalidArgumentException
   *   When the given order is not a cart.
   */
  protected function hasCartAccess(
    OrderInterface $order,
    $operation,
    AccountInterface $account
  ) {
    if (!$order->get('cart')->value) {
      throw new \InvalidArgumentException(
        'Trying to check cart access on an order that is not a cart.'
      );
    }

    // Users are allowed to create, update or delete split items if they have
    // permissions to update the cart.
    if (in_array($operation, ['create', 'delete'])) {
      $operation = 'update';
    }

    return \Drupal::service('commerce_cart_advanced.cart_access_control_handler')
      ->access($order, $operation, $account);
  }

  /**
   * Builds the access result as required to be returned by the access handler.
   *
   * It allows adding a final access check on top of the result built by the
   * parent access control handler, and it adds the split item and the order to
   * the cacheable dependencies.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $result
   *   The prebuilt access result to which to add the extra controls.
   * @param \Drupal\gcommerce_split\Entity\SplitItemInterface $split_item
   *   The split item for which we are checking access.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order that the split item belongs to.
   * @param bool $has_access
   *   An additional check to add; access will be allowed only when TRUE.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The final access result.
   */
  protected function buildAccessResult(
    AccessResultInterface $result,
    SplitItemInterface $split_item,
    OrderInterface $order,
    $has_access
  ) {
    return $result
      ->andIf(AccessResult::allowedIf($has_access))
      ->addCacheableDependency($split_item)
      ->addCacheableDependency($order);
  }

}
