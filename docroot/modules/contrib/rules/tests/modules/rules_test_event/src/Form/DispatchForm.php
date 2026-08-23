<?php

declare(strict_types=1);

namespace Drupal\rules_test_event\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rules_test_event\Event\GenericEvent;
use Drupal\rules_test_event\Event\GetterEvent;
use Drupal\rules_test_event\Event\PlainEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Acquires input, wraps it in a Task object, and queues it for processing.
 */
class DispatchForm extends FormBase {

  /**
   * The event_dispatcher service.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Constructor.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event_dispatcher service.
   */
  public function __construct(EventDispatcherInterface $dispatcher) {
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rules_test_event.dispatcher_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['event'] = [
      '#type' => 'radios',
      '#options' => [
        'plain' => $this->t('PlainEvent'),
        'generic' => $this->t('GenericEvent'),
        'getter' => $this->t('GetterEvent'),
      ],
      '#description' => $this->t('Choose Event to dispatch for testing.'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Dispatch event',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Event object type selected via the form radio buttons.
    $choice = $form_state->getValue('event');

    $event = match($choice) {
      'plain'   => new PlainEvent(),
      'generic' => new GenericEvent('Test subject', [
        'publicProperty' => 'public property',
        'protectedProperty' => 'protected property',
        'privateProperty' => 'private property',
      ]),
      'getter'  => new GetterEvent(),
    };

    // Dispatch the chosen event.
    $this->dispatcher->dispatch($event, $event::EVENT_NAME);
  }

}
