<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base checkout flow class.
 *
 * Checkout flows should extend this class only if they don't want to use
 * checkout panes. Otherwise they should extend CheckoutFlowWithPanesBase.
 */
abstract class CheckoutFlowBase extends PluginBase implements CheckoutFlowInterface, ContainerFactoryPluginInterface {

  /**
   * The current order
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The current step ID.
   *
   * @var string
   */
  protected $stepId;

  /**
   * Constructs a new CheckoutFlowBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    $this->order = $route_match->getParameter('commerce_order');
    $this->stepId = $route_match->getParameter('step');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $steps = $this->getVisibleSteps();
    $form['#title'] = $steps[$this->stepId]['label'];
    $form['actions'] = $this->actions($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $this->order->save();

    if ($next_step_id = $this->getNextStepId()) {
      $form_state->setRedirect('commerce_checkout.form', [
        'commerce_order' => $this->order->id(),
        'step' => $next_step_id,
      ]);

      if ($next_step_id == 'complete') {
        // @todo Fire a checkout complete event.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function previousForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
      'step' => $this->getPreviousStepId(),
    ]);
  }

  /**
   * Builds the actions element for the current form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The actions element.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = [
      '#type' => 'actions',
    ];
    $steps = $this->getVisibleSteps();
    $previous_step_id = $this->getPreviousStepId();
    if ($previous_step_id && isset($steps[$previous_step_id]['previous_label'])) {
      $actions['previous'] = [
        '#type' => 'submit',
        '#value' => $steps[$previous_step_id]['previous_label'],
        '#submit' => [
          '::previousForm',
        ],
      ];
    }
    $next_step_id = $this->getNextStepId();
    if ($next_step_id && isset($steps[$next_step_id]['next_label'])) {
      $actions['next'] = [
        '#type' => 'submit',
        '#value' => $steps[$next_step_id]['next_label'],
        '#button_type' => 'primary',
        '#submit' => ['::submitForm'],
      ];
    }
    // Hide the actions element if it has no buttons.
    $actions['#access'] = isset($actions['previous']) || isset($actions['next']);
    // Don't allow the user to leave the offsite_payment page.
    if ($this->stepId == 'offsite_payment') {
      $actions['#access'] = FALSE;
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Each checkout flow plugin defines its own steps.
    // These two steps are always expected to be present.
    return [
      'offsite_payment' => [
        'label' => $this->t('Payment'),
        'next_label' => $this->t('Continue to payment'),
      ],
      'complete' => [
        'label' => $this->t('Complete'),
        'next_label' => $this->t('Pay and complete purchase'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleSteps() {
    // All steps are visible by default.
    return $this->getSteps();
  }

  /**
   * Gets the previous step ID.
   *
   * @return string|null
   *   The previous step, or NULL if there is none.
   */
  protected function getPreviousStepId() {
    $step_ids = array_keys($this->getVisibleSteps());
    $current_index = array_search($this->stepId, $step_ids);
    return isset($step_ids[$current_index - 1]) ? $step_ids[$current_index - 1] : NULL;
  }

  /**
   * Gets the next step ID.
   *
   * @return string|null
   *   The next step ID, or NULL if there is none.
   */
  protected function getNextStepId() {
    $step_ids = array_keys($this->getVisibleSteps());
    $current_index = array_search($this->stepId, $step_ids);
    return isset($step_ids[$current_index + 1]) ? $step_ids[$current_index + 1] : NULL;
  }

}
