<?php

namespace Drupal\gcommerce_order;

use Drupal\group\Plugin\GroupContentPermissionProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines permissions for the order group content enabler plugin.
 *
 * @I Move permissions to permission providers for other plugins as well
 *    type     : task
 *    priority : low
 *    labels   : deprecation, permission
 */
class GroupOrderPermissionProvider extends GroupContentPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    $plugin_id,
    array $definition
  ) {
    $instance = parent::createInstance($container, $plugin_id, $definition);

    // Orders do not implement the `EntityOwnerInterface`. They do have a
    // creator however and we want to define ownership-based permissions
    // e.g. view own orders and view any order etc.
    $instance->implementsOwnerInterface = TRUE;

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions() {
    $permissions = parent::buildPermissions();

    // Cart permissions.
    // @I Define cart permissions only if `gcommerce_cart` is enabled
    //    type     : improvement
    //    priority : low
    //    labels   : permission
    $prefix = 'Entity:';
    $plugin_id = $this->pluginId;
    $permissions["view any $plugin_id cart"] = $this->buildPermission(
      "$prefix View any %entity_type carts"
    );
    $permissions["view own $plugin_id cart"] = $this->buildPermission(
      "$prefix View own %entity_type carts"
    );
    $permissions["update any $plugin_id cart"] = $this->buildPermission(
      "$prefix Update any %entity_type carts"
    );
    $permissions["update own $plugin_id cart"] = $this->buildPermission(
      "$prefix Update own %entity_type carts"
    );

    // Checkout permissions.
    // @I Define checkout permissions only if `gcommerce_checkout` is enabled
    //    type     : improvement
    //    priority : low
    //    labels   : permission
    $prefix = 'Entity:';
    $plugin_id = $this->pluginId;
    $permissions["checkout any $plugin_id entity"] = $this->buildPermission(
      "$prefix Checkout any %entity_type entities"
    );
    $permissions["checkout own $plugin_id entity"] = $this->buildPermission(
      "$prefix Checkout own %entity_type entities"
    );

    return $permissions;
  }

}
