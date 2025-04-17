<?php

namespace Drupal\wake_events\EventSubscriber\Form\Alter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Drupal\recurring_events_registration\RegistrationCreationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Our event subscriber.
 */
class EventRegistrationForm implements EventSubscriberInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;


  /**
   * The creation service.
   *
   * @var \Drupal\recurring_events_registration\RegistrationCreationService
   */
  protected $creationService;

  /**
   * Construct an RegistrantForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\recurring_events_registration\RegistrationCreationService $creation_service
   *   The registrant creation service.
   */
  public function __construct(RouteMatchInterface $route_match, RegistrationCreationService $creation_service) {
    $this->routeMatch = $route_match;
    $this->creationService = $creation_service;
  }

  /**
   * Alter form.
   *
   * @param \Drupal\core_event_dispatcher\Event\Form\FormAlterEvent $event
   *   The event.
   */
  public function alterForm(FormAlterEvent $event): void {

    $form = &$event->getForm();

    // Override registration closed message.
    // @see recurring_events_registration/src/Form/RegistrantForm.php
    if ($form["#id"] !== "registrant-default-add-form") {
      return;
    }

    /***
     * Recurring_events_registration has difficult logic for registration
     * open & close scenarios. Here we do not override the logic but, if
     * a registration date hasn't arrived, provde a better message.
     */
    if (empty($form["notifications"]["registration_closed"]["message"])) {
      return;
    }

    $this->creationService->setEventInstance(
          $this->routeMatch->getParameter("eventinstance")
        );

    $dates = $this->creationService->registrationOpeningClosingTime();
    if (empty($dates["reg_open"])) {
      return;
    }

    $now = new DrupalDateTime();
    $isOpeningSoon = $now->getTimestamp() < $dates["reg_open"]->getTimestamp();

    if ($isOpeningSoon) {
      // @see recurring_events_registration/src/Form/RegistrantForm.php.
      $container =& $form["notifications"]["registration_closed"];
      $container["title"] = [];
      $type = $this->creationService->getRegistrationType() === 'series' ? 'series' : 'event';
      $container["message"]["#markup"] = "Registration for this $type has not yet opened.";
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FormHookEvents::FORM_ALTER => "alterForm",
    ];
  }

}
