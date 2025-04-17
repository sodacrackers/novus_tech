<?php

namespace Drupal\wake_events\EventSubscriber\FormAlter;

use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Drupal\honeypot\HoneypotServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registrant form alter to add Honeypot.
 */
class HoneypotForEventsFormAlter implements EventSubscriberInterface {

  /**
   * Honeypot service.
   *
   * @var \Drupal\honeypot\HoneypotServiceInterface
   */
  private $honeypot;

  /**
   * Registrant form alter constructor.
   *
   * @param \Drupal\honeypot\HoneypotServiceInterface $honeypot
   *   Honeypot service..
   */
  public function __construct(HoneypotServiceInterface $honeypot) {
    $this->honeypot = $honeypot;
  }

  /**
   * Alter form.
   *
   * @param \Drupal\core_event_dispatcher\Event\Form\FormAlterEvent $event
   *   The event.
   */
  public function alterForm(FormAlterEvent $event): void {
    if (str_contains($event->getFormId(), 'registrant_')) {
      $this->honeypot
        ->addFormProtection(
          $event->getForm(),
          $event->getFormState(),
          ['honeypot', 'time_restriction']
        );
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FormHookEvents::FORM_ALTER => 'alterForm',
    ];
  }

}
