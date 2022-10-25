<?php

namespace Drupal\google_calendar_events;

use Google\Service\Calendar;

class GCResponseProvider {

  /**
   * Module config object
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * Google client connection object.
   *
   * @var \Google\Client
   */
  protected $googleClient;

  /**
   * Page limit on requests.
   *
   * @var integer
   */
  protected $pageSize;

  /**
   * Constructs an object with default values.
   */
  public function __construct() {

    $config = \Drupal::config('google_calendar_events.settings');

    $this->config = $config;
    $this->loadCreds();
    $this->pageSize = 20;

  }

  /**
   * Retrieves a response object containing the list of calendars.
   *
   * @return object|null
   */
  public function getCalendarList() {

    $cal = new Calendar($this->googleClient);
    $calendars = $cal->calendarList->listCalendarList();

    return $calendars;

  }

  /**
   * Gets a list of events for the configured calendar.
   *
   * @param string $minDate RFC3339 formatted date string for lower bound of date range to query events for.
   * @param string $maxDate RFC3339 formatted date string for upper bound of date range to query events for.
   * @param string $nextPageToken Option parameter for a next page of results.
   * @return object|null
   */
  public function getEventsList($minDate, $maxDate, $nextPageToken = NULL) {

    $eventsList = NULL;
    $requestArgs = [
      'timeMin' => $minDate,
      'timeMax' => $maxDate,
      'maxResults' => 20
    ];

    if(empty($minDate) || empty($maxDate)) {

      \Drupal::logger('google_calendar_events')
          ->error('Tried to get events without min or max date.');

    }
    else {

      $cal = new Calendar($this->googleClient);
      $targetCal = $this->config->get('calendar');

      if(!empty($nextPageToken)) {

        $requestArgs['pageToken'] = $nextPageToken;

      }

      $eventsList = $cal->events->listEvents($targetCal, $requestArgs);

    }

    return $eventsList;

  }

  /**
   * Authenticates the $googleClient object so that it's ready to use
   */
  public function loadCreds() {

    $keyNames = \Drupal::service('key.repository')->getKeys();
    $subject = $this->config->get('subject');

    if(array_key_exists('google_calendar_service_key', $keyNames) && !empty($subject)) {

      $tmpSK = (array)json_decode(\Drupal::service('key.repository')
        ->getKey('google_calendar_service_key')
        ->getKeyValue());
      $this->googleClient = new \Google\Client();
      $this->googleClient->setAuthConfig($tmpSK);

      // Scope is hardcoded as there is no intention of making this module have anything
      // other than read only access to Google Calendar
      $this->googleClient
        ->setScopes(['https://www.googleapis.com/auth/calendar.readonly']);
      $this->googleClient->setSubject($subject);

    }
    else {
      \Drupal::logger('google_calendar_events')
          ->error('Tried using module without a subject or service key.');
    }
  }

}
