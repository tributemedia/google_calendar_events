<?php

namespace Drupal\google_calendar_events\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;

/**
 * Checks Google Calendar for new events and updates to already retrieved ones. If an update or new event is needed,
 * a job is queued for the CalendarEventWorker.
 *
 * @QueueWorker(
 *  id = "google_calendar_events_ceq",
 *  title = @Translation("Google Calendar Event Worker"),
 *  cron = {"time" = 30}
 * )
 */
class CalendarEventWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {

    $config = \Drupal::service('config.factory')->getEditable('google_calendar_events.settings');
    $retrievedEvents = $config->get('google_events');

    if(isset($item->op) && $item->op == 'CREATE') {

      // Create and save the new node
      $startDate = new \DateTime($item->start);
      $endDate = new \DateTime($item->end);

      $newNode = Node::create([
        'type' => 'mdc_event',
        'title' => $item->title,
        'body' => [
          'summary' => '',
          'value' => $item->body,
        ],
        'field_event_date' => [
          'value' => strval($startDate->getTimestamp()),
          'end_value' => strval($endDate->getTimestamp()),
          'duration' => strval(($endDate->getTimestamp() - $startDate->getTimestamp()) / 60),
        ]
      ]);
      $newNode->save();

      // Update the list of retrieved events
      $retrievedEvents[$item->gcid] = $newNode->id();
      $config->set('google_events', $retrievedEvents);
      $config->save();

      $msg = "Creating the following event: " . $item->title;

      \Drupal::logger('google_calendar_events')
          ->notice($msg);

    }
    else if(isset($item->op) && $item->op == 'UPDATE') {

      $node = Node::load($retrievedEvents[$item->gcid]);
      $startDate = new \DateTime($item->start);
      $endDate = new \DateTime($item->end);

      $node->set('field_event_date', [
        'value' => strval($startDate->getTimestamp()),
        'end_value' => strval($endDate->getTimestamp()),
        'duration' => strval(($endDate->getTimestamp() - $startDate->getTimestamp()) / 60),
      ]);
      $node->save();

      \Drupal::logger('google_calendar_events')
          ->notice('Updated the following event: ' . $node->getTitle());

    }
    else {

      \Drupal::logger('google_calendar_events')
          ->error('No op specified on item.');

    }

  }

}
