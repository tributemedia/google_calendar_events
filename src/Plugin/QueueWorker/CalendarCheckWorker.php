<?php

namespace Drupal\google_calendar_events\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\google_calendar_events\GCResponseProvider;
use Drupal\node\Entity\Node;

/**
 * Checks Google Calendar for new events and updates to already retrieved ones. If an update or new event is needed,
 * a job is queued for the CalendarEventWorker.
 *
 * @QueueWorker(
 *  id = "google_calendar_events_ccq",
 *  title = @Translation("Google Calendar Event Checker"),
 *  cron = {"time" = 30}
 * )
 */
class CalendarCheckWorker extends QueueWorkerBase {
  const INTERVAL = 12 * 60 * 60;

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {

    $config = \Drupal::config('google_calendar_events.settings');
    $queueFactory = \Drupal::service('queue');
    $myQueue = $queueFactory->get('google_calendar_events_ccq');
    $ceq = $queueFactory->get('google_calendar_events_ceq');
    $retrievedEvents = $config->get('google_events');
    $rp = new GCResponseProvider();
    $date = date(\DateTimeInterface::RFC3339,time());
    $year = intval(explode('-', $date)[0]);
    $minDate = ($year - 1) . substr($date, 4);
    $maxDate = ($year + 1) . substr($date, 4);
    $eventsList = NULL;

    if(empty($item->nextPageToken)) {

      $eventsList = $rp->getEventsList($minDate, $maxDate);

    }
    else {

      $eventsList = $rp->getEventsList($minDate, $maxDate, $item->nextPageToken);

    }

    // If this has never been ran, retrieved events will be null.
    // Setting to an empty array to avoid problems later on in this function.
    if(is_null($retrievedEvents)) {

      $retrievedEvents = [];

    }

    if(!is_null($eventsList)) {

      $events = $eventsList->items;

      foreach($events as $event) {

        if(array_key_exists($event->id, $retrievedEvents)) {

          $localEvent = Node::load($retrievedEvents[$event->id]);
          $localStart = $localEvent->get('field_event_date')->getValue()[0]['value'];
          $localEnd = $localEvent->get('field_event_date')->getValue()[0]['end_value'];
          $eventStart = \DateTime::createFromFormat(
            \DateTimeInterface::RFC3339,
            $event->start['dateTime']);
          $eventEnd = \DateTime::createFromFormat(
            \DateTimeInterface::RFC3339,
            $event->end['dateTime']);

          // At this time, events are only updated if the scheduled date
          // has changed.
          if(intval($localStart) != $eventStart->getTimestamp() ||
        intval($localEnd) != $eventEnd->getTimestamp()) {

            $toCreate = new \stdClass();
            $toCreate->op = 'UPDATE';
            $toCreate->gcid = $event->id;
            $toCreate->start = $event->start['dateTime'];
            $toCreate->end = $event->end['dateTime'];

            $ceq->createItem($toCreate);

          }

        }
        else {

          // If there are missing important values, do not queue a job for that event
          if(!is_null($event->start) && !is_null($event->end) && !is_null($event->summary)) {

            $toCreate = new \stdClass();
            $toCreate->op = 'CREATE';
            $toCreate->gcid = $event->id;
            $toCreate->title = $event->summary;
            $toCreate->body = $event->description;
            $toCreate->start = $event->start['dateTime'];
            $toCreate->end = $event->end['dateTime'];

            $ceq->createItem($toCreate);

          }

        }

      }

      if(!empty($eventsList->nextPageToken)) {

        $nextPage = new \stdClass();
        $nextPage->nextPageToken = $eventsList->nextPageToken;

        $myQueue->createItem($nextPage);

      }

    }
    else {

      \Drupal::logger('google_calendar_events')
          ->error('Could not get a list of events. Check the module settings ' .
          'to make sure everything is configured correctly.');

    }

  }

  /**
   * Sets the next execution time to check for events.
   */
  public static function setNextExecTime() {
    \Drupal::state()->set('google_calendar_events.next_exec',
      time() + self::INTERVAL);
  }
}
