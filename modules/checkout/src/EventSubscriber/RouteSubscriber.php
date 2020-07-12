<?php

namespace Drupal\gcommerce_checkout\EventSubscriber;

use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Routing\RouteBuildEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener for altering the checkout controller access check method.
 */
class RouteSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      RoutingEvents::ALTER => ['onAlterRoutes', -10],
    ];
    return $events;
  }

  /**
   * Alters the checkout controller to use a custom access method.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route alter event.
   */
  public function onAlterRoutes(RouteBuildEvent $event) {
    $route = $event->getRouteCollection()->get('commerce_checkout.form');
    if (!$route) {
      return;
    }

    $route->setRequirement(
      '_custom_access',
      '\Drupal\gcommerce_checkout\Controller\Checkout::checkAccess'
    );
  }

}
