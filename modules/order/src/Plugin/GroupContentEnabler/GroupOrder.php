<?php

namespace Drupal\gcommerce_order\Plugin\GroupContentEnabler;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\commerce_order\Entity\OrderType;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a content enabler for commerce orders.
 *
 * @GroupContentEnabler(
 *   id = "group_commerce_order",
 *   label = @Translation("Group order"),
 *   description = @Translation("Adds orders to groups both publicly and privately."),
 *   entity_type_id = "commerce_order",
 *   entity_access = TRUE,
 *   reference_label = @Translation("Title"),
 *   reference_description = @Translation("The title of the order to add to the group"),
 *   deriver = "Drupal\gcommerce_order\Plugin\GroupContentEnabler\GroupOrderDeriver",
 *   handlers = {
 *     "permission_provider" = "Drupal\gcommerce_order\GroupOrderPermissionProvider",
 *   }
 * )
 *
 * @I Use dependency injection for loading order types and current user
 *    type     : task
 *    priority : normal
 *    labels   : coding-standards, dependency-injection, order
 * @I Consider providing a plugin that permits all order bundles
 *    type     : feature
 *    priority : normal
 *    labels   : content-enabler, order
 * @I Add a base content enabler plugin that can be reused for all entities
 *    type     : task
 *    priority : normal
 *    labels   : content-enabler
 */
class GroupOrder extends GroupContentEnablerBase {

  /**
   * {@inheritdoc}
   */
  public function getGroupOperations(GroupInterface $group) {
    $account = \Drupal::currentUser();
    $plugin_id = $this->getPluginId();

    if (!$group->hasPermission("create $plugin_id entity", $account)) {
      return [];
    }

    $type = $this->getEntityBundle();
    $route_params = ['group' => $group->id(), 'plugin_id' => $plugin_id];

    return [
      "gcommerce-order-create-$type" => [
        'title' => $this->t(
          'Create @type',
          ['@type' => $this->getOrderType()->label()]
        ),
        'url' => new Url('entity.group_content.create_form', $route_params),
        'weight' => 30,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['entity_cardinality'] = 1;

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Disable the entity cardinality field as the functionality of this module
    // relies on a cardinality of 1. We don't just hide it, though, to keep a UI
    // that's consistent with other content enabler plugins.
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $this->t(
      "This field has been disabled by the plugin to guarantee the functionality
      that's expected of it."
    ) . '</em>';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $dependencies['config'][] = 'commerce_order.commerce_order_type.' . $this->getEntityBundle();

    return $dependencies;
  }

  /**
   * Retrieves the order type this plugin supports.
   *
   * @return \Drupal\commerce_order\Entity\OrderTypeInterface
   *   The order type this plugin supports.
   */
  protected function getOrderType() {
    return OrderType::load($this->getEntityBundle());
  }

}
