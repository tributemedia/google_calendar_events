<?php

namespace Drupal\google_calendar_events\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\google_calendar_events\GCResponseProvider;
use Google\Service\Calendar;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GCEConfigForm extends FormBase {

  /**
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * Dependency injected constructor
   * @param MessengerInterface $messenger
   */
  public function __construct(MessengerInterface $messenger) {

    $this->messenger = $messenger;
  }

  /**
   * @param ContainerInterface $container
   * @return FormBase|GMBLocationConfigForm
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('messenger')
    );

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'google_calendar_events_config_form';

  }

  public function buildForm(array $form, FormStateInterface $formState = NULL) {

    $form = [];
    $config = \Drupal::config('google_calendar_events.settings');
    $responseProvider = new GCResponseProvider();
    $keyNames = \Drupal::service('key.repository')->getKeys();
    $subject = $config->get('subject');

    $form['settings_container'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => 'settings-container',
      ),
      '#tree' => TRUE,
    );

    // We need to be able to poll the Calendar API to properly load the
    // config form. Therefore, if there is no service key, inform the
    // user they need to provide one to continue.
    if(array_key_exists('google_calendar_service_key', $keyNames) &&
      !empty($subject)) {

      $calendars = $responseProvider->getCalendarList()->items;
      $calendarList = [];
      $selectedCalendar = $config->get('calendar');
      $syncRange = [
        'years_1' => '1 Year',
        'years_2' => '2 Years'
      ];
      $selectedRange = $config->get('sync_range');

      foreach($calendars as $calendar) {
        $calendarList[$calendar->id] = $calendar->summary;
      }

      $this->appendSubjectField($form, $subject);

      $form['settings_container']['calendar'] = array(
        '#type' => 'select',
        '#title' => 'Calendar',
        '#description' => 'The calendar to sync events from.',
        '#options' => $calendarList,
        '#default_value' => $selectedCalendar
      );

      $form['settings_container']['sync_range'] = array(
        '#type' => 'select',
        '#title' => 'Sync Date Range',
        '#description' => 'The amount of time before and after today in ' .
          'which calendar events will be synced and updated.',
        '#options' => $syncRange,
        '#default_value' => $selectedRange
      );

      $this->appendSaveButton($form);

    }
    else if(array_key_exists('google_calendar_service_key', $keyNames) &&
      empty($subject)) {

      $this->messenger->addWarning('Cannot proceed without a subject. Please ' .
        'enter one in the field provided and submit.');
      $this->appendSubjectField($form, $subject);
      $this->appendSaveButton($form);

    }
    else {
      // Replace error message with FormattableMarkup that contains link
      // to key module config. See following for an example:
      // https://drupal.stackexchange.com/questions/216402/how-to-use-html-tags-in-form-error-messages
      $form['nothing'] = ['#markup' => ''];
      $this->messenger->addError('There is no key with the name ' .
        'google_calendar_service_key. Please add your service key in the ' .
        'key module with that name.', []);
    }



    return $form;

  }

  public function submitForm(array &$form, FormStateInterface $formState) {

    $config = \Drupal::service('config.factory')
      ->getEditable('google_calendar_events.settings');
    $settings = $formState->getValues()['settings_container'];

    if(isset($settings['subject']) && $settings['subject'] != $config->get('subject')) {
      $config->set('subject', $settings['subject'])->save();
    }

    if(isset($settings['calendar']) && $settings['calendar'] != $config->get('calendar')) {
      $config->set('calendar', $settings['calendar'])->save();
    }

    if(isset($settings['sync_range']) && $settings['sync_range'] != $config->get('sync_range')) {
      $config->set('sync_range', $settings['sync_range'])->save();
    }

    $this->messenger->addMessage('Settings updated.');
  }

  private function appendSubjectField(array &$form, $subject) {

    $form['settings_container']['subject'] = array(
      '#type' => 'textfield',
      '#title' => 'API Subject',
      '#description' => 'Enter the email of a user who has authorization to ' .
        'use the service key.',
      '#default_value' => $subject
    );

  }

  private function appendSaveButton(array &$form) {

    $form['save'] = array(
      '#type' => 'submit',
      '#value' => 'Save',
    );

  }

}
