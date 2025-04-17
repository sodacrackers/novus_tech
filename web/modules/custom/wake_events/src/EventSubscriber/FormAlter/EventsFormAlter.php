<?php

namespace Drupal\wake_events\EventSubscriber\FormAlter;

use Drupal\core_event_dispatcher\Event\Form\FormIdAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class for form hooks.
 */
class EventsFormAlter implements EventSubscriberInterface {

  /**
   * Alter form.
   *
   * Param \Drupal\core_event_dispatcher\Event\Form\FormIdAlterEvent $event
   *   The event.
   */
  public function alterForm(FormIdAlterEvent $event): void {

    $form = &$event->getForm();

    // Collapse metatags on events and as default.
    if (!empty($form['field_meta_tags'])) {
      if (!empty($form['field_meta_tags']['widget'][0]['schema_event']['#open'])) {
        $form['field_meta_tags']['widget'][0]['schema_event']['#open'] = FALSE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'hook_event_dispatcher.form_alter' => 'alterForm',

    ];
  }

}
