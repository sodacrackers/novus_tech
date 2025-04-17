<?php

namespace Drupal\wake_events\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $admin_routes = [
      'entity.eventseries.add_form',
      'entity.eventseries.add_page',
      'entity.eventseries.edit_form',
      'entity.eventseries.delete_form',
      'entity.eventseries.clone_form',
      'entity.eventinstance.edit_form',
      'entity.eventinstance.delete_form',
      'entity.eventinstance.clone_form',
    ];

    foreach ($admin_routes as $admin_route) {
      if ($route = $collection->get($admin_route)) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
