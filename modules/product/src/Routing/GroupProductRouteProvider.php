<?php

namespace Drupal\gcommerce_product\Routing;

use Symfony\Component\Routing\Route;

/**
 * Provides routes for `group_commerce_product` group content.
 *
 * @I Use dependency injection for loading product types
 *    type     : task
 *    priority : normal
 *    labels   : coding-standards, dependency-injection, product
 */
class GroupProductRouteProvider {

  /**
   * Provides the shared collection route for group product plugins.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of routes keyed by route name.
   */
  public function getRoutes() {
    $product_types = \Drupal::service('entity_type.manager')
      ->getStorage('commerce_product_type')
      ->loadMultiple();

    // If there are no product types yet, we cannot have any plugin IDs and
    // therefore no routes either.
    if (!$product_types) {
      return [];
    }

    $plugin_ids = [];
    $permissions_existing = [];
    $permissions_new = [];

    foreach ($product_types as $name => $product_type) {
      $plugin_id = "group_commerce_product:$name";

      $plugin_ids[] = $plugin_id;
      $permissions_existing[] = "create $plugin_id content";
      $permissions_new[] = "create $plugin_id entity";
    }

    return array_merge(
      $this->buildExistingProductRoute($plugin_ids, $permissions_existing),
      $this->buildNewProductRoute($plugin_ids, $permissions_new)
    );
  }

  /**
   * Builds the route for adding an existing product to a group.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of routes keyed by route name.
   */
  protected function buildExistingProductRoute(
    array $plugin_ids,
    array $permissions
  ) {
    $route = new Route('group/{group}/commerce_product/add');
    $route->setDefaults([
      '_title' => 'Add existing product',
      '_controller' => '\Drupal\gcommerce_product\Controller\GroupProductController::addPage',
    ]);
    $route->setRequirement(
      '_group_installed_content',
      implode('+', $plugin_ids)
    );
    $route->setRequirement('_group_permission', implode('+', $permissions));
    $route->setOption('_group_operation_route', TRUE);

    return [
      'entity.group_content.group_commerce_product_relate_page' => $route,
    ];
  }

  /**
   * Builds the route for adding a new product to a group.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of routes keyed by route name.
   */
  protected function buildNewProductRoute(
    array $plugin_ids,
    array $permissions
  ) {
    $route = new Route('group/{group}/commerce_product/create');
    $route->setDefaults([
      '_title' => 'Add new product',
      '_controller' => '\Drupal\gcommerce_product\Controller\GroupProductController::addPage',
      'create_mode' => TRUE,
    ]);
    $route->setRequirement(
      '_group_installed_content',
      implode('+', $plugin_ids)
    );
    $route->setRequirement('_group_permission', implode('+', $permissions));
    $route->setOption('_group_operation_route', TRUE);

    return [
      'entity.group_content.group_commerce_product_add_page' => $route,
    ];
  }

}
