<?php

namespace Drupal\gcommerce_context\Hook;

use Drupal\gcommerce_context\Context\ManagerInterface;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Holds methods implementing hook_query_alter or hook_query_alter().
 */
class QueryAlter {

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
   * Constructs a new QueryAlter object.
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
   * Implements hook_query_TAG_alter().
   *
   * We alter the query issued by the cart provider to load the current user's
   * carts. We want to load the carts that belong to the current usner's current
   * context instead.
   *
   * @param \Drupal\Core\Database\Query\AlterableInterface $query
   *   A Query object describing the composite parts of a SQL query.
   */
  public function commerceCartLoadData(AlterableInterface $query) {
    if (!$query instanceof SelectInterface) {
      return;
    }

    // If the user does not have a current context, assume personal context. In
    // that case, we don't need to alter the query in any way.
    $context = $this->contextManager->get();
    if (!$context) {
      return;
    }

    // Remove the condition that limits carts to the ones owned by the user.
    $conditions = &$query->conditions();
    foreach ($conditions as $index => &$condition) {
      if (!is_array($condition)) {
        continue;
      }
      if ($condition['field'] === 'o.uid') {
        unset($conditions[$index]);
        break;
      }
    }

    // Alter the query to load only carts that belong to a group that the
    // current user is a member of as well.
    // @I Review if we need access control as well i.e. check group permissions
    //    type     : bug
    //    priority : high
    //    labels   : context, security
    $data_table = $this->entityTypeManager
      ->getDefinition('group_content')
      ->getDataTable();
    $query->leftJoin(
      $data_table,
      'gcfd',
      'o.order_id = gcfd.entity_id'
    );
    $query->leftJoin(
      $data_table,
      'gcfdu',
      'gcfd.gid = gcfdu.gid'
    );
    // @I Programmatically load the group content types
    //    type     : bug
    //    priority : high
    //    labels   : context
    //    notes    : Right now we use the group content types provided by the
    //               `gcommerce_customer` module.
    $query->condition('gcfd.type', 'group_content_type_740ce7f502d20');
    $query->condition('gcfdu.type', 'group_content_type_08a1eda14e3b4');
    $query->condition('gcfd.gid', $context->id());
    $query->condition('gcfdu.entity_id', $this->currentUser->id());
  }

}
