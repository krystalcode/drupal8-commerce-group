<?php

namespace Drupal\gcommerce_cart\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\group\Entity\GroupInterface;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Holds methods implementing hooks related to entity access.
 */
class EntityAccess {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EntitySave object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements hook_commerce_cart_access().
   *
   * The default cart permissions and access control provided by the Commerce
   * Cart Advanced module allow access to carts if the user is the owner of the
   * cart and has the `update own commerce_order cart` permission, or if the
   * user has the `update any commerce_order cart` permission.
   *
   * We need to give access to users when the user has the right permissions
   * within the group that the cart belongs to as well.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The cart order.
   * @param string $operation
   *   The operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function commerceCartAccess(
    OrderInterface $order,
    $operation,
    AccountInterface $account
  ) {
    // Load the group contents that the order belongs to, if any.
    $group_content_types = $this->entityTypeManager
      ->getStorage('group_content_type')
      ->loadByContentPluginId('group_commerce_order:' . $order->bundle());
    if (!$group_content_types) {
      $this->returnNeutral($order);
    }

    $group_content_type_ids = array_column($group_content_types, 'id');
    $group_contents = $this->entityTypeManager
      ->getStorage('group_content')
      ->loadByProperties([
        'entity_id' => $order->id(),
        'type' => array_keys($group_content_types),
      ]);

    // If the user has permissions in at least one group from the ones that the
    // cart belongs to, grant access.
    foreach ($group_contents as $group_content) {
      $group = $group_content->getGroup();
      $plugin_id = $group_content->getContentPlugin()->getPluginId();

      $result = $this->checkEntityOwnerGroupPermissions(
        $order,
        $operation,
        $account,
        $group,
        $plugin_id
      );
      if ($result->isAllowed()) {
        return $result->addCacheableDependency($order);
      }
    }

    return $this->returnNeutral($order);
  }

  /**
   * Checks access for operations that support entity ownership.
   *
   * Operations supporting entity ownership are the ones that provide separate
   * group permission for the operating on own entities and operating on any
   * entity.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to check access for.
   * @param string $operation
   *   The operation access should be checked for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group that the order belongs to.
   * @param string $plugin_id
   *   The ID of the content enabler plugin that defines the relation between
   *   the order and the group.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkEntityOwnerGroupPermissions(
    OrderInterface $order,
    $operation,
    AccountInterface $account,
    GroupInterface $group,
    $plugin_id
  ) {
    if ($group->hasPermission("$operation any $plugin_id cart", $account)) {
      return AccessResult::allowed();
    }

    if ($account->id() != $order->getCustomerId()) {
      return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
    }

    if ($group->hasPermission("$operation own $plugin_id cart", $account)) {
      return AccessResult::allowed()->addCacheableDependency($order);
    }

    return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
  }

  /**
   * Build the access result for the cases where we don't give access.
   *
   * We return a neutral result instead of a negative one to maintain the
   * ability of other access handlers to give a positive result. For example, if
   * the user is not given permissions here but is a site administrator and has
   * the global `update any commerce_order cart`, they should be given access to
   * the cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function returnNeutral(OrderInterface $order) {
    return AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($order);
  }

}
