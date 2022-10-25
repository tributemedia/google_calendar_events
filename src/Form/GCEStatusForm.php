<?php

namespace Drupal\google_calendar_events\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\google_calendar_events\Plugin\QueueWorker\CalendarCheckWorker;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GCEStatusForm extends FormBase {

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

    return 'google_calendar_events_status_form';

  }

  public function buildForm(array $form, FormStateInterface $formState = NULL) {

    $form = [];
    $queueFactory = \Drupal::service('queue');
    $config = \Drupal::config('google_calendar_events.settings');
    $nextRunTime =
      \Drupal::state()->get('google_calendar_events.next_exec');
    $ccq = $queueFactory->get('google_calendar_events_ccq');
    $ceq = $queueFactory->get('google_calendar_events_ceq');

    if(empty($nextRunTime)) {

      $nextRunTime = 0;

    }

    $ccqText = 'Items in Calendar Check Queue (CCQ): ' .
      $ccq->numberOfItems();
    $ceqText = 'Items in Calendar Event Queue (CEQ): ' .
      $ceq->numberOfItems();
    $nextRunTimeText = 'Next run time: ';

    if(empty($nextRunTime)) {

      $nextRunTimeText .= '0';

    }
    else {

      $nextRunTimeText .= date("D M j G:i:s T Y", $nextRunTime);

    }

    $form['stats'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => 'stats-container',
      ),
      '#tree' => TRUE,
    );

    $form['stats']['calendar_check_queue'] = [
      '#type' => 'label',
      '#title' => $ccqText,
    ];

    $form['stats']['calendar_event_queue'] = [
      '#type' => 'label',
      '#title' => $ceqText,
    ];

    $form['stats']['next_run'] = [
      '#type' => 'label',
      '#title' => $nextRunTimeText,
    ];

    $form['start_flow'] = [
      '#type' => 'submit',
      '#value' => 'Check Events'
    ];

    $form['clear_queues'] = [
      '#type' => 'submit',
      '#value' => 'Clear Queues'
    ];

    return $form;

  }

  public function submitForm(array &$form, FormStateInterface $formState) {

    $queueFactory = \Drupal::service('queue');
    $ccq = $queueFactory->get('google_calendar_events_ccq');
    $ceq = $queueFactory->get('google_calendar_events_ceq');

    switch($formState->getValues()['op']) {

      case 'Clear Queues':
        $ccq->deleteQueue();
        $ceq->deleteQueue();
        $this->messenger->addMessage('Queues cleared.');
        break;
      case 'Check Events':
        if($ccq->numberOfItems() > 0) {
          $this->messenger->addError('CCQ already has a job queued.');
        }
        else {
          $ccq->createItem(new \stdClass());

          CalendarCheckWorker::setNextExecTime();
          $this->messenger->addMessage('Workflow started. ' .
            'Run cron to start checking for new events.');
        }
        break;

    }

  }

}
