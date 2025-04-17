<?php

namespace Drupal\wake_events\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Jsvrcek\ICS\CalendarExport;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Model\Description\Location;
use Jsvrcek\ICS\Utility\Formatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * The RegistrantController class.
 */
class ICalFeed extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The feed name.
   *
   * @var string
   */
  protected string $feedName = "WakeGOV Events";

  /**
   * The formatter.
   *
   * @var Jsvrcek\ICS\Utility\Formatter
   */
  private Formatter $formatter;

  /**
   * Events filter value.
   *
   * @var string
   */
  protected string $department;

  /**
   * Events filter value.
   *
   * @var string
   */
  protected string $audience;

  /**
   * Events filter value.
   *
   * @var string
   */
  protected string $category;

  /**
   * Events filter value.
   *
   * @var string
   */
  protected string $location;

  /**
   * The CalendarExport.
   *
   * @var Jsvrcek\ICS\CalendarExport
   */
  private CalendarExport $calendarExport;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * EntityStorageInterface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The time zone displayed.
   *
   * @param \DateTimeZone $displayTimeZone
   */
  protected \DateTimeZone $displayTimeZone;

  /**
   * The storage time zone.
   *
   * @param \DateTimeZone $storageTimeZone
   */
  protected \DateTimeZone $storageTimeZone;

  /**
   * Constructs a RegistrantController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory, RequestStack $request_stack, TranslationInterface $string_translation, EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache_backend) {

    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->stringTranslation = $string_translation;
    $this->cacheBackend = $cache_backend;
    $this->storage = $entity_type_manager->getStorage("eventinstance");

    // Get the filters.
    $this->department = $this->currentRequest->get("department");
    $this->category = $this->currentRequest->get("category");
    $this->location = $this->currentRequest->get("location");
    $this->audience = $this->currentRequest->get("audience");

    // Get needed timezones.
    $label = $this->configFactory->get('system.date')->get('timezone.default');
    $this->displayTimeZone = new \DateTimeZone($label);
    $this->storageTimeZone = new \DateTimeZone("UTC");

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get("database"),
      $container->get('config.factory'),
      $container->get("request_stack"),
      $container->get("string_translation"),
      $container->get("entity_type.manager"),
      $container->get("cache.default")
    );

  }

  /**
   * Our iCal router endpoint.
   */
  public function go(): Response {

    // Setup calendar.
    $calendar = new Calendar();
    $calendar->setName($this->feedName . " -- " . $_SERVER["REQUEST_URI"]);
    $calendar->setProdId("-//WAKE//WAKE EVENTS//EN");
    $calendar->setTimezone($this->displayTimeZone);

    // Add each event.
    foreach ($this->getEvents() as $data) {
      try {
        $storageKey = "ical:event:" . $data->id;
        $expiresTags = [
          "eventinstance:" . $data->id,
          "eventseries:" . $data->eventseries_id,
        ];

        if ($cache = $this->cacheBackend->get($storageKey)) {
          $calendarEvent = $cache->data;
        }
        else {
          $calendarEvent = $this->prepareCalEvent($data->id, $data->eventseries_id);
          $this->cacheBackend->set($storageKey, $calendarEvent, CacheBackendInterface::CACHE_PERMANENT, $expiresTags);
        }

        $calendar->addEvent($calendarEvent);
      }
      catch (\Exception $e) {
        echo $e->getMessage();
      }
    }

    $calendarExport = new CalendarExport(new CalendarStream(), new Formatter());
    $calendarExport->setDateTimeFormat("local-tz");
    $calendarExport->addCalendar($calendar);

    // Correct the errant timezone in BEGIN:VTIMEZONE.
    $content = $calendarExport->getStream();
    $content = str_replace("DTSTART;TZID=+00:00:", "DTSTART:", $content);

    // Return the response.
    $response = new Response($content);
    $response->headers->set("Content-Type", "text/calendar");

    return $response;
  }

  /**
   * Helper function.
   */
  private function prepareCalEvent(int $event_instance_id): CalendarEvent {

    // Load the events.
    $event = $this->storage->load($event_instance_id);
    $series = $event->getEventSeries();

    // Get values.
    $uuid = $event->get("uuid")->value;
    $title = $event->get("title")->value;
    $start = $this->getStartTime($event);
    $end = $this->getEndTime($event);
    $body = $this->getBody($event, $series);
    $url = $event->toUrl("canonical", ["absolute" => TRUE])->toString();

    $location = new Location();
    $place = $this->getLocation($event, $series);
    $location->setName($place);

    // Create ical item.
    $export = new CalendarEvent();
    $export->setUid($uuid)
      ->setStart($start)
      ->setEnd($end)
      ->setSummary($title)
      ->setDescription($body)
      ->setUrl($url)
      ->addLocation($location);

    return $export;
  }

  /**
   * Helper function.
   */
  private function getEvents(): array {

    // Query series for the instance fields.
    $query = $this->database->select("eventinstance_field_data", "d")
      ->fields("d", ["id", "eventseries_id"])
      ->condition("d.status", 1)
      ->condition("d.date__value", date("Y-m-d"), ">");

    $query->leftJoin("eventseries_field_data", "s", "s.id = d.eventseries_id");

    // Add filters, if any.
    if (is_numeric($this->department)) {
      $query->leftJoin("eventseries__field_department", "esfdp", "esfdp.entity_id = d.eventseries_id");
      $query->condition("field_department_target_id", $this->department);
    }
    if (is_numeric($this->category)) {
      $query->leftJoin('eventseries__field_event_type', 'esfdet', 'esfdet.entity_id = d.eventseries_id');
      $query->condition("field_event_type_target_id", $this->category);
    }
    if (is_numeric($this->location)) {
      $query->leftJoin("eventseries__field_location_reference", "esflr", "esflr.entity_id = d.eventseries_id");
      $query->condition("field_location_reference_target_id", $this->location);
    }
    if ($this->audience) {
      $audiences = explode(",", $this->audience);
      $audiences = array_filter($audiences, "is_numeric");
      $query->leftJoin("eventseries__field_event_audience", "esfdea", "esfdea.entity_id = d.eventseries_id");
      $query->condition("field_event_audience_target_id", $audiences, "IN");
    }

    // Limit results.
    $query
      ->orderBy("d.date__value")
      ->range(0, 300);

    return $query->execute()->fetchAll();
  }

  /**
   * Helper function.
   */
  private function getStartTime(EntityInterface $event) {

    $dates = $event->get("date")?->first()?->getValue();

    // Get stored value.
    $start = new \DateTime(($dates["value"] ?? NULL));

    // Adjust it to display time.
    $diff = $this->displayTimeZone->getOffset($start)
      - $this->storageTimeZone->getOffset($start);

    $start->modify($diff . ' seconds')
      ->setTimezone($this->displayTimeZone);

    return $start;
  }

  /**
   * Helper function.
   */
  private function getEndTime(EntityInterface $event) {

    $dates = $event->get("date")?->first()?->getValue();
    if (empty($dates["end_value"])) {
      return $this->getStartTime($event);
    }

    // Get stored value.
    $end = new \DateTime($dates["end_value"]);

    // Adjust it to display time.
    $diff = $this->displayTimeZone->getOffset($end)
      - $this->storageTimeZone->getOffset($end);

    $end->modify($diff . ' seconds')
      ->setTimezone($this->displayTimeZone);

    return $end;
  }

  /**
   * Helper function.
   */
  private function getLocation(EntityInterface $event, EntityInterface $series = NULL) {

    $location = $this->getNames($event, "field_location_reference") ??
     $this->getNames($series, "field_location_reference");

    return $location;
  }

  /**
   * Helper function.
   */
  private function getBody(EntityInterface $event, EntityInterface $series = NULL) {

    $body = $event->get("body")->value
      . "\n\n" . $series->get("body")->value;

    // Terms.
    $value = $this->getNames($series, "field_event_audience");
    if ($value) {
      $body .= "\n\nAudience: " . $value;
    }
    $value = $this->getNames($series, "field_event_type");
    if ($value) {
      $body .= "\n\nType: " . $value;
    }
    $value = $this->getNames($series, "field_department");
    if ($value) {
      $body .= "\n\nDepartment: " . $value;
    }

    $body .= "\n\nView more at " . $event->toUrl(
      "canonical", ["absolute" => TRUE])->toString();

    return $this->getCleanString($body);
  }

  /**
   * Helper function.
   */
  private function getNames(EntityInterface $event, string $field) {

    // Inherited.
    $entities = $event?->get($field)?->referencedEntities() ?? [];

    $values = [];
    foreach ($entities as $entity) {
      if (method_exists($entity, "getName")) {
        $values[] = $entity->getName();
      }
      if (method_exists($entity, "getTitle")) {
        $values[] = $entity->getTitle();
      }
    }

    return implode(", ", $values);
  }

  /**
   * Helper function.
   */
  private function getCleanString(string $value = "") {

    $value = strip_tags($value);
    $value = htmlspecialchars_decode($value);
    return $value;
  }

}
