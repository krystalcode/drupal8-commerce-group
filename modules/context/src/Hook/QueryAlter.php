<?php

namespace Drupal\gcommerce_context\Hook;

use Drupal\gcommerce_context\Context\ManagerInterface;
use Drupal\gcommerce_context\Exception\InvalidConfigurationException;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Holds methods implementing hook_query_alter or hook_query_alter().
 */
class QueryAlter {

  /**
   * The module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\gcommerce_context\Context\ManagerInterface $context_manager
   *   The shopping context manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account proxy.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ManagerInterface $context_manager,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->config = $config_factory->get('gcommerce_context.settings');
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
    if (!$this->config->get('status')) {
      return;
    }
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

    [$order_plugin_ids, $user_plugin_ids] = $this->getPluginIds();
    $query->condition('gcfd.type', $order_plugin_ids, 'IN');
    $query->condition('gcfdu.type', $user_plugin_ids, 'IN');
    $query->condition('gcfd.gid', $context->id());
    $query->condition('gcfdu.entity_id', $this->currentUser->id());
  }

  /**
   * Loads the plugin IDs for order and user memberships.
   *
   * We only load plugin IDs that define group content types for the group type
   * configured to act as the context. Also, we assume the default user
   * membership plugin and the order membership plugin provided by
   * `gcommerce_order`. It is extremely rare to have other plugins for the same
   * entities (`user`, `commerce_order`), and if there are they would most
   * likely serve a different purpose, so it's not worth making things more
   * complicated.
   *
   * @return array
   *   An array containing the order membership plugin IDs as its first element,
   *   and the user membership plugin IDs as its second element.
   */
  protected function getPluginIds() {
    $group_type_id = $this->config->get('group_context.group_type');
    $group_content_types = $this->entityTypeManager
      ->getStorage('group_content_type')
      ->loadByProperties(
        ['group_type' => $group_type_id]
      );

    $order_plugin_ids = [];
    $user_plugin_ids = [];
    foreach ($group_content_types as $group_content_type) {
      $plugin_id = $group_content_type->getContentPluginId();
      if ($plugin_id === 'group_membership') {
        $user_plugin_ids[] = $group_content_type->id();
        continue;
      }

      if (strpos($plugin_id, 'group_commerce_order:') === 0) {
        $order_plugin_ids[] = $group_content_type->id();
      }
    }

    $this->validatePluginIds(
      $order_plugin_ids,
      $user_plugin_ids,
      $group_type_id
    );

    return [$order_plugin_ids, $user_plugin_ids];
  }

  /**
   * Validates that plugins are installed for orders/users for the group type.
   *
   * @param string[] $order_plugin_ids
   *   The plugin IDs for orders.
   * @param string[] $user_plugin_ids
   *   The plugin IDs for users.
   * @param string $group_type_id
   *   The ID of the group type that acts as the context.
   *
   * @throws \Drupal\gcommerce\Exception\InvalidConfigurationException
   *   When there are no plugins installed for making orders or users available
   *   as group content to the group type with the given ID.
   */
  protected function validatePluginIds(
    array $order_plugin_ids,
    array $user_plugin_ids,
    $group_type_id
  ) {
    if (!$order_plugin_ids) {
      throw new InvalidConfigurationException(
        sprintf(
          'No order types are configured to be available as group content for the `%s` group type.',
          $group_type_id
        )
      );
    }
    if (!$user_plugin_ids) {
      throw new InvalidConfigurationException(
        sprintf(
          'Users are not configured to be available as group content for the `%s` group type.',
          $group_type_id
        )
      );
    }
  }

}
