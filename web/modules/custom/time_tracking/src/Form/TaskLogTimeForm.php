<?php

declare(strict_types=1);

namespace Drupal\time_tracking\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a time write-off form for a single task.
 */
final class TaskLogTimeForm extends FormBase {

  /**
   * Constructs a TaskLogTimeForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $account,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static
  {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('datetime.time'),
    );

    $instance->setMessenger($container->get('messenger'));

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'time_tracking_task_log_time_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\node\NodeInterface|null $task
   *   The task node coming from the route parameter.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $task = NULL): array {
    $form_state->set('task_id', (int) $task->id());

    $form['task'] = [
      '#type' => 'item',
      '#title' => $this->t('Task'),
      '#markup' => $task->label(),
    ];

    $form['hours'] = [
      '#type' => 'number',
      '#title' => $this->t('Hours'),
      '#description' => $this->t('Number of hours to log.'),
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    $form['log_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Log date'),
      '#default_value' => date(DateTimeItemInterface::DATE_STORAGE_FORMAT, $this->time->getRequestTime()),
      '#required' => TRUE,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows' => 4,
    ];

    $form['over_estimate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I am logging more than estimated'),
    ];

    $form['over_estimate_reason'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reason for exceeding the estimate'),
      '#maxlength' => 255,
      '#states' => [
        'visible' => [
          ':input[name="over_estimate"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="over_estimate"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Log time'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $hours = $form_state->getValue('hours');
    if (!is_numeric($hours) || (float) $hours <= 0) {
      $form_state->setErrorByName('hours', $this->t('Hours must be greater than zero.'));
    }

    $log_date = (string) $form_state->getValue('log_date');
    $today = date(DateTimeItemInterface::DATE_STORAGE_FORMAT, $this->time->getRequestTime());
    if ($log_date !== '' && $log_date > $today) {
      $form_state->setErrorByName('log_date', $this->t('The log date cannot be in the future.'));
    }

    $reason = trim((string) $form_state->getValue('over_estimate_reason'));
    if ($form_state->getValue('over_estimate') && $reason === '') {
      $form_state->setErrorByName('over_estimate_reason', $this->t('Please explain why you log more than estimated.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $over_estimate = (bool) $form_state->getValue('over_estimate');
    $reason = trim((string) $form_state->getValue('over_estimate_reason'));

    $time_log = $this->entityTypeManager->getStorage('time_log')->create([
      'task' => $form_state->get('task_id'),
      'uid' => $this->account->id(),
      'hours' => (string) $form_state->getValue('hours'),
      'log_date' => (string) $form_state->getValue('log_date'),
      'notes' => (string) $form_state->getValue('notes'),
      'over_estimate_reason' => $over_estimate ? $reason : NULL,
    ]);
    $time_log->save();

    $this->messenger()->addStatus($this->t('Logged @hours h.', [
      '@hours' => $form_state->getValue('hours'),
    ]));
    $form_state->setRedirect('entity.node.canonical', ['node' => $form_state->get('task_id')]);
  }

}
