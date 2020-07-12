<?php

namespace Drupal\gcommerce_checkout\Controller;

use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\group\Access\GroupPermissionCheckerInterface;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an access method for the checkout form page.
 */
class Checkout implements ContainerInjectionInterface {

  /**
   * The cart session.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  protected $cartSession;

  /**
   * The group content storage.
   *
   * @var \Drupal\group\Entity\Storage\GroupContentStorageInterface
   */
  protected $groupContentStorage;

  /**
   * The group permission checker.
   *
   * @var \Drupal\group\Access\GroupPermissionCheckerInterface
   */
  protected $groupPermissionChecker;

  /**
   * Constructs a new Checkout object.
   *
   * @param \Drupal\commerce_cart\CartSessionInterface $cart_session
   *   The cart session.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\Access\GroupPermissionCheckerInterface $group_permission_checker
   *   The group permission checker.
   */
  public function __construct(
    CartSessionInterface $cart_session,
    EntityTypeManagerInterface $entity_type_manager,
    GroupPermissionCheckerInterface $group_permission_checker
  ) {
    $this->cartSession = $cart_session;
    $this->groupPermissionChecker = $group_permission_checker;

    $this->groupContentStorage = $entity_type_manager
      ->getStorage('group_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_session'),
      $container->get('entity_type.manager'),
      $container->get('group_permission.checker')
    );
  }

  /**
   * Checks access for the checkout form page.
   *
   * This method is used instead of the default access check method provided by
   * the `commerce_checkout` module so that it can additionally check if the
   * user has permission to checkout an order in the context of the group(s)
   * that the order belongs to, if any.
   *
   * The logic is the following:
   * - If the order does not belong to any groups, users can checkout only their
   *   own non-empty carts.
   * - If the order does belong to one or more groups, users can checkout their
   *   own or any non-empty carts if they have permissions to do so in at least
   *   one of the order's group.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(
    RouteMatchInterface $route_match,
    AccountInterface $account
  ) {
    $order = $route_match->getParameter('commerce_order');
    if ($order->getState()->getId() === 'canceled') {
      return AccessResult::forbidden()->addCacheableDependency($order);
    }

    // First check if the user is given access by any of the order's groups.
    $access_check = $this->groupAccessCheck($account, $order);

    // If the order does not have any groups, we do the standard access check as
    // it would be done by the Commerce Checkout module.
    if ($access_check === NULL) {
      $access_check = $this->customerAccessCheck($account, $order);
    }

    return AccessResult::allowedIf($access_check)
      ->andIf(AccessResult::allowedIf($order->hasItems()))
      // The group checkout permission specifically gives users access to
      // checkout orders that belong to groups, but they still need to have the
      // global checkout permission.
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access checkout'))
      ->addCacheableDependency($order);
  }

  /**
   * Checks checkout access in the context of an order's groups.
   *
   * It allows access if the user either has permission to checkout any order in
   * at least one of the order's groups, or is the owner and has permission to
   * checkout their own orders in at least one of the order's groups.
   *
   * This behavior does not change for anonymous users; groups can be configured
   * to give access to group content to anonymous users and there could be use
   * cases where group orders are visible to anonymous users and can be checked
   * by anonymous users as well.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool|null
   *   TRUE if the user has access to the order, FALSE otherwise, NULL if the
   *   order does not belong to any group.
   */
  protected function groupAccessCheck(
    AccountInterface $account,
    OrderInterface $order
  ) {
    $group_contents = $this->groupContentStorage->loadByEntity($order);
    if (!$group_contents) {
      return;
    }

    $access_check = FALSE;
    $is_owner = $this->isOwner($account, $order);

    foreach ($group_contents as $group_content) {
      $group = $group_content->getGroup();
      $plugin_id = $group_content->getContentPlugin()->getPluginId();

      $access_check = $this->groupPermissionChecker
        ->hasPermissionInGroup(
          "checkout any $plugin_id entity",
          $account,
          $group
        );
      if (!$access_check && $is_owner) {
        $access_check = $this->groupPermissionChecker
          ->hasPermissionInGroup(
            "checkout own $plugin_id entity",
            $account,
            $group
          );
      }

      if ($access_check) {
        break;
      }
    }

    return $access_check;
  }

  /**
   * Checks checkout access outside of any group context.
   *
   * Users only have access to checkout orders they own.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   TRUE if the user has access to the order, FALSE otherwise.
   */
  protected function customerAccessCheck(
    AccountInterface $account,
    OrderInterface $order
  ) {
    return $this->isOwner($account, $order);
  }

  /**
   * Checks whether the user is the owner of the order.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   TRUE if the user is the owner of the order, FALSE otherwise.
   */
  protected function isOwner(
    AccountInterface $account,
    OrderInterface $order
  ) {
    // Authenticated; the order has the customer ID.
    if ($account->isAuthenticated()) {
      return $account->id() == $order->getCustomerId();
    }

    // Anonymous; the order is in the user session.
    $active_cart = $this->cartSession->hasCartId(
      $order->id(),
      CartSession::ACTIVE
    );
    $completed_cart = $this->cartSession->hasCartId(
      $order->id(),
      CartSession::COMPLETED
    );

    return $active_cart || $completed_cart;
  }

}
