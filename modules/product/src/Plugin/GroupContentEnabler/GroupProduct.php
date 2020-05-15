<?php

namespace Drupal\gcommerce_product\Plugin\GroupContentEnabler;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\commerce_product\Entity\ProductType;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a content enabler for commerce products.
 *
 * @GroupContentEnabler(
 *   id = "group_commerce_product",
 *   label = @Translation("Group product"),
 *   description = @Translation("Adds products to groups both publicly and privately."),
 *   entity_type_id = "commerce_product",
 *   entity_access = TRUE,
 *   reference_label = @Translation("Title"),
 *   reference_description = @Translation("The title of the product to add to the group"),
 *   deriver = "Drupal\gcommerce_product\Plugin\GroupContentEnabler\GroupProductDeriver"
 * )
 *
 * @I Use dependency injection for loading product types and current user
 *    type     : task
 *    priority : normal
 *    labels   : coding-standards, dependency-injection, product
 * @I Consider providing a plugin that permits all product bundles
 *    type     : feature
 *    priority : normal
 *    labels   : content-enabler, product
 */
class GroupProduct extends GroupContentEnablerBase {

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
      "gcommerce-product-create-$type" => [
        'title' => $this->t(
          'Create @type',
          ['@type' => $this->getProductType()->label()]
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
    $dependencies['config'][] = 'commerce_product.type.' . $this->getEntityBundle();

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTargetEntityPermissions() {
    $permissions = parent::getTargetEntityPermissions();
    $plugin_id = $this->getPluginId();

    // Add a 'view unpublished' permission by re-using most of the 'view' one.
    $original = $permissions["view $plugin_id entity"];
    $permissions["view unpublished $plugin_id entity"] = [
      'title' => str_replace('View ', 'View unpublished ', $original['title']),
    ] + $original;

    return $permissions;
  }

  /**
   * Retrieves the group product this plugin supports.
   *
   * @return \Drupal\commerce_product\Entity\ProductTypeInterface
   *   The product type this plugin supports.
   */
  protected function getProductType() {
    return ProductType::load($this->getEntityBundle());
  }

}
