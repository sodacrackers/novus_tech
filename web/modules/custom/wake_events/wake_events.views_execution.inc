<?php

/**
 * @file
 * Provide views runtime hooks for wake_events.module.
 */

use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Implements hook_views_query_alter().
 */
function wake_events_views_query_alter(ViewExecutable $view, Sql $query) {
  if ($view->storage->get('id') === 'related_events' && $view->current_display === 'block_1') {
    $eventseries_id = NULL;
    $eventseries = \Drupal::routeMatch()->getParameter('eventseries');
    $event_instance = \Drupal::routeMatch()->getParameter('eventinstance');

    if (!is_null($eventseries)) {
      $eventseries_id = $eventseries->id();
    }

    // Get the event series ID from the event instance if we don't already have
    // it.
    if (!is_null($event_instance) && is_null($eventseries_id)) {
      $eventseries_id = $event_instance->eventseries_id[0]->target_id;
    }

    // Only show related events from different event series.
    if ($eventseries_id) {
      $query->addWhere(0, 'eventseries_id', $eventseries_id, '<>');
    }

    // We need the ability to show related events based upon the department and
    // the event type taxonomy, and this is not possible at this time within the
    // views UI, because core does not support taxonomy contextual filters for
    // non node entities. So we are doing it programmatically based on the page
    // that is being viewed.
    $department = $event_type = NULL;

    // If we are on an event instance, then use the inherited fields for the
    // department and event type fields.
    if (!empty($event_instance)) {
      if ($event_instance->hasField('department')) {
        $department = $event_instance->department->target_id;
      }

      if ($event_instance->hasField('event_type')) {
        $event_type = $event_instance->event_type->target_id;
      }
    }

    // If we are on an event series page, then use the standard department and
    // event type fields from the series.
    if (!empty($eventseries)) {
      if ($eventseries->hasField('field_department')) {
        $department = $eventseries->field_department->target_id;
      }

      if ($eventseries->hasField('field_event_type')) {
        $event_type = $eventseries->field_event_type->target_id;
      }
    }

    if (!empty($department) || !empty($event_type)) {
      $join_configuration = [
        'type'       => 'LEFT',
        'table'      => 'eventseries_field_data',
        'field'      => 'id',
        'left_table' => 'eventinstance_field_data',
        'left_field' => 'eventseries_id',
        'operator'   => '=',
      ];
      $join = Views::pluginManager('join')->createInstance('standard', $join_configuration);
      $relationship = $query->addRelationship('eventseries_field_data', $join, 'eventseries_field_data');
      $query->addTable('eventseries_field_data', $relationship, $join, 'eventseries_field_data');

      // If we have a department on the current page, then limit the query to
      // use that department.
      if (!empty($department)) {
        $join_configuration = [
          'type'       => 'INNER',
          'table'      => 'eventseries__field_department',
          'field'      => 'entity_id',
          'left_table' => 'eventseries_field_data',
          'left_field' => 'id',
          'operator'   => '=',
        ];
        $join = Views::pluginManager('join')->createInstance('standard', $join_configuration);
        $relationship = $query->addRelationship('eventseries__field_department', $join, 'eventseries__field_department');

        $query->addWhere(0, 'eventseries__field_department.field_department_target_id', $department);
      }

      // If we have an event type on the current page, then limit the query to
      // use that event type.
      if (!empty($event_type)) {
        $join_configuration = [
          'type'       => 'INNER',
          'table'      => 'eventseries__field_event_type',
          'field'      => 'entity_id',
          'left_table' => 'eventseries_field_data',
          'left_field' => 'id',
          'operator'   => '=',
        ];
        $join = Views::pluginManager('join')->createInstance('standard', $join_configuration);
        $relationship = $query->addRelationship('eventseries__field_event_type', $join, 'eventseries__field_event_type');

        $query->addWhere(0, 'eventseries__field_event_type.field_event_type_target_id', $event_type);
      }
    }
  }
}
