<?php

namespace Drupal\gcommerce_order\Routing;

use Symfony\Component\Routing\Route;

/**
 * Provides routes for `group_commerce_order` group content.
 *
 * @I Use dependency injection for loading order types
 *    type     : task
 *    priority : normal
 *    labels   : coding-standards, dependency-injection, order
 * @I Add a base route provider that can be reused for all entities
 *    type     : task
 *    priority : normal
 *    labels   : routing, refactoring
 */
class GroupOrderRouteProvider {

  /**
   * Provides the shared collection route for group order plugins.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of routes keyed by route name.
   */
  public function getRoutes() {
    $order_types = \Drupal::service('entity_type.manager')
      ->getStorage('commerce_order_type')
      ->loadMultiple();

    // If there are no order types yet, we cannot have any plugin IDs and
    // therefore no routes either.
    if (!$order_types) {
      return [];
    }

    $plugin_ids = [];
    $permissions_existing = [];
    $permissions_new = [];

    foreach ($order_types as $name => $order_type) {
      $plugin_id = "group_commerce_order:$name";

      $plugin_ids[] = $plugin_id;
      $permissions_existing[] = "create $plugin_id content";
      $permissions_new[] = "create $plugin_id entity";
    }

    return $this->buildExistingOrderRoute($plugin_ids, $permissions_existing);
  }

  /**
   * Builds the route for adding an existing order to a group.
   *
   * @param array $plugin_ids
   *   The array of installed plugins for the entity.
   * @param array $permissions
   *   The array containing the permissions to relate existing entities to the
   *   group for the installed plugins.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of routes keyed by route name.
   */
  protected function buildExistingOrderRoute(
    array $plugin_ids,
    array $permissions
  ) {
    $route = new Route('group/{group}/commerce_order/add');
    $route->setDefaults([
      '_title' => 'Add existing order',
      '_controller' => '\Drupal\gcommerce_order\Controller\GroupOrderController::addPage',
    ]);
    $route->setRequirement(
      '_group_installed_content',
      implode('+', $plugin_ids)
    );
    $route->setRequirement('_group_permission', implode('+', $permissions));
    $route->setOption('_group_operation_route', TRUE);

    return [
      'entity.group_content.group_commerce_order_relate_page' => $route,
    ];
  }

}
