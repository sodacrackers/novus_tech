# Wake events.

## iCal Feed.

Provides controller and endpoints to view iCal calendar feeds, with filters.
Makes use of a composer library, `Jsvrcek\ICS`, for iCal data models. We use
this custom solution because Drupal views formatters are expensive and currently
blocked by `recurring_events` use of field inheritance.


Docs:

- https://github.com/jasvrcek/ICS
- https://icalendar.org/validator.html
- https://datatracker.ietf.org/doc/html/rfc5545

Reference:

- http://tools.ietf.org/html/rfc5545

Not considered/ Blocked:

  Views iCal - Blocked because of `Exception: Base table type not supported. At
  the moment, Views iCal only supports nodes and Search API indexes in
  Drupal\views_ical\Plugin\views\row\IcalFieldsWizard->getEntity() (line 624 of
  modules/contrib/views_ical/src/Plugin/views/row/IcalFieldsWizard.php).`
  https://www.drupal.org/project/views_ical
