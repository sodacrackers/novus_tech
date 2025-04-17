<?php

namespace Drupal\wake_events\Controller;
// @codingStandardsIgnoreFile
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * The RegistrantController class.
 */
class RegistrantController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs a RegistrantController object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation')
    );
  }

  /**
   * Return a dynamic page title for a Registration success.
   *
   * @param Drupal\recurring_events\Entity\EventInstance $event_instance
   *   The event instance for which the registrant is created.
   * @param string $type
   *   The type of registration: waitlist or full.
   *
   * @return string
   *   The page title.
   */
  public function getSuccessTitle(EventInstance $eventinstance, $type = 'full') {
    if (!empty($eventinstance)) {
      return $eventinstance->title->value;
    }
    return $this->stringTranslation->translate('Registration Confirmation');
  }

  /**
   * Return a dynamic page title for a Registrant.
   *
   * @param Drupal\recurring_events\Entity\EventInstance $event_instance
   *   The event instance for which the registrant is created.
   * @param string $type
   *   The type of registration: waitlist or full.
   */
  public function successPage(EventInstance $eventinstance, $type = 'full') {
    $build = [];
    switch ($type) {
      case 'waitlist':
        $theme = 'wake_events_registrant_waitlist';
        break;

      case 'full':
      default:
        $theme = 'wake_events_registrant_full';
        break;
    }
    $build['heading'] = [
      '#type' => 'theme',
      '#theme' => $theme,
      '#eventinstance' => $eventinstance,
      '#eventseries' => $eventinstance->getEventSeries(),
    ];

    return $build;
  }

}
