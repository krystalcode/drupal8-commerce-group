<?php

namespace Drupal\gcommerce_context\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\gcommerce_context\Context\ManagerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Holds methods implementing hooks related to entity saving.
 */
class EntitySave {

  /**
   * The shopping context manager.
   *
   * @var \Drupal\gcommerce_context\Context\ManagerInterface
   */
  protected $contextManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EntitySave object.
   *
   * @param \Drupal\gcommerce_context\Context\ManagerInterface $context_manager
   *   The shopping context manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account proxy.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ManagerInterface $context_manager,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->contextManager = $context_manager;
    $this->currentUser = $current_user->getAccount();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   *
   * We add new cart orders to the user's current context, if any.
   *
   * Orders can be created in different circumstances outside of the process of
   * adding products to the cart. For example, a store manager can create an
   * order on behalf of another user. We therefore take the following
   * precautions.
   * - We do nothing if the order is not a cart.
   * - We do nothing if the current user is not the order's customer.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order that was created.
   *
   * @I Review default UI for creating orders
   *    type     : improvement
   *    priority : normal
   *    labels   : context, ux
   *    notes    : Allow selecting a context when creating an order, making
   *               available the contexts of the order's customer.
   */
  public function commerceOrderInsert(OrderInterface $order) {
    if ($this->currentUser->id() != $order->getCustomerId()) {
      return;
    }

    if (!$order->get('cart')) {
      return;
    }

    $context = $this->contextManager->get();
    if (!$context) {
      return;
    }

    $content_storage = $this->entityTypeManager->getStorage('group_content');
    $content = $content_storage->createForEntityInGroup(
      $order,
      $context,
      'group_commerce_order:' . $order->bundle()
    );
    $content_storage->save($content);
  }

}
