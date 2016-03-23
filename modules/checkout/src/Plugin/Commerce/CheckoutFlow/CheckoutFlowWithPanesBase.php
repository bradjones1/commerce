<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\CheckoutPaneManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base checkout flow that uses checkout panes.
 */
abstract class CheckoutFlowWithPanesBase extends CheckoutFlowBase {

  /**
   * The checkout pane manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutPaneManager
   */
  protected $paneManager;

  /**
   * Constructs a new CheckoutFlowWithPanesBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pane_id
   *   The plugin_id for the plugin instance.
   * @param mixed $pane_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\commerce_checkout\CheckoutPaneManager $pane_manager
   *   The checkout pane manager.
   */
  public function __construct(array $configuration, $pane_id, $pane_definition, RouteMatchInterface $route_match, CheckoutPaneManager $pane_manager) {
    parent::__construct($configuration, $pane_id, $pane_definition, $route_match);

    $this->paneManager = $pane_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pane_id, $pane_definition) {
    return new static(
      $configuration,
      $pane_id,
      $pane_definition,
      $container->get('current_route_match'),
      $container->get('plugin.manager.commerce_checkout_pane')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Gets the configuration for the given pane.
   *
   * Pane configuration is stored in the main configuration, grouped by step.
   *
   * @param string $pane_id
   *   The pane ID.
   *
   * @return array
   *   The pane configuration.
   */
  protected function getPaneConfiguration($pane_id) {
    $pane_configuration = [];
    $steps = array_keys($this->getSteps());
    $configuration = $this->getConfiguration();
    foreach ($configuration as $step => $panes) {
      if (in_array($step, $steps) && !empty($panes[$pane_id])) {
        $pane_configuration = $panes[$pane_id];
        break;
      }
    }

    return $pane_configuration;
  }

  /**
   * Get the regions for the checkout pane overview table.
   *
   * @return array
   *   The table regions, keyed by step id.
   */
  protected function getTableRegions() {
    $regions = [];
    foreach ($this->getSteps() as $step_id => $step) {
      $regions[$step_id] = [
        'title' => $step['label'],
        'message' => $this->t('No pane is displayed.'),
      ];
    }
    $regions['_disabled'] = [
      'title' => $this->t('Disabled', [], ['context' => 'Plural']),
      'message' => $this->t('No pane is hidden.'),
    ];

    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Initialize the panes and group them by step.
    $panes = [];
    foreach ($this->paneManager->getDefinitions() as $pane_id => $pane_definition) {
      /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface $pane */
      $pane = $this->paneManager->createInstance($pane_id, $this->getPaneConfiguration($pane_id));
      $step = $pane->getConfiguration()['step'];
      $panes[$step][$pane_id] = $pane;
    }

    $wrapper_id = Html::getUniqueId('checkout-pane-overview-wrapper');
    $form['panes'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Pane'),
        $this->t('Weight'),
        $this->t('Step'),
        ['data' => $this->t('Settings'), 'colspan' => 2],
      ],
      '#attributes' => [
        'class' => ['checkout-pane-overview'],
        // Used by the JS code when attaching behaviors.
        'id' => 'checkout-pane-overview',
      ],
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#wrapper_id' => $wrapper_id,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'pane-weight',
        ],
        [
          'action' => 'match',
          'relationship' => 'step',
          'group' => 'pane-step',
          'subgroup' => 'pane-step',
          'source' => 'pane-id',
        ],
      ],
    ];
    foreach ($this->getTableRegions() as $step_id => $region) {
      $form['panes']['region-' . $step_id] = [
        '#attributes' => [
          'class' => ['region-title'],
          'no_striping' => TRUE,
        ],
      ];
      $form['panes']['region-' . $step_id]['title'] = [
        '#markup' => $region['title'],
        '#wrapper_attributes' => ['colspan' => 5],
      ];
      $form['panes']['region-' . $step_id . '-message'] = [
        '#attributes' => [
          'class' => [
            'region-message',
            'region-' . Html::getClass($step_id) . '-message',
            empty($panes[$step_id]) ? 'region-empty' : 'region-populated',
          ],
          'no_striping' => TRUE,
        ],
      ];
      $form['panes']['region-' . $step_id . '-message']['message'] = [
        '#markup' => $region['message'],
        '#wrapper_attributes' => ['colspan' => 5],
      ];
      foreach ($panes[$step_id] as $pane_id => $pane) {
        $form['panes'][$pane_id] = $this->buildPaneRow($pane, $form, $form_state);
      }
    }

    return $form;
  }

  /**
   * Builds the table row structure for a checkout pane.
   *
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface $pane
   *   The checkout pane.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A table row array.
   */
  protected function buildPaneRow(CheckoutPaneInterface $pane, array $form, FormStateInterface $form_state) {
    $pane_id = $pane->getPluginId();
    $label = $pane->getLabel();
    $regions = array_keys($this->getTableRegions());
    $pane_row = [
      '#attributes' => [
        'class' => ['draggable', 'tabledrag-leaf'],
      ],
      'human_name' => [
        '#plain_text' => $label,
      ],
      'weight' => [
        '#type' => 'textfield',
        '#title' => $this->t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => $pane->getWeight(),
        '#size' => 3,
        '#attributes' => [
          'class' => ['pane-weight'],
        ],
      ],
      'step_wrapper' => [
        'step' => [
          '#type' => 'select',
          '#title' => $this->t('Checkout step for @title', ['@title' => $label]),
          '#title_display' => 'invisible',
          '#options' => array_combine($regions, $regions),
          '#default_value' => 'hidden',
          '#empty_value' => '',
          '#attributes' => ['class' => ['js-pane-step', 'pane-step']],
          '#parents' => ['panes', $pane_id, 'step'],
        ],
        'hidden_name' => [
          '#type' => 'hidden',
          '#default_value' => $pane_id,
          '#attributes' => ['class' => ['pane-id']],
        ],
      ],
    ];

    $base_button = [
      '#submit' => [
        [get_class($this), 'multistepSubmit'],
      ],
      '#ajax' => [
        'callback' => [get_class($this), 'multistepAjax'],
        'wrapper' => $form['panes']['#wrapper_id'],
      ],
      '#pane_id' => $pane_id,
    ];

    if ($form_state->get('pane_configuration_edit') == $pane_id) {
      $pane_row['settings'] = [
        '#type' => 'container',
        '#wrapper_attributes' => ['colspan' => 2],
        '#attributes' => [
          'class' => ['pane-configuration-edit-form'],
        ],
        'form' => $pane->buildConfigurationForm([], $form_state),
        'actions' => [
          '#type' => 'actions',
          'save_settings' => $base_button + [
              '#type' => 'submit',
              '#button_type' => 'primary',
              '#name' => $pane_id . '_pane_configuration_update',
              '#value' => $this->t('Update'),
              '#op' => 'update',
            ],
          'cancel_settings' => $base_button + [
              '#type' => 'submit',
              '#name' => $pane_id . '_plugin_settings_cancel',
              '#value' => $this->t('Cancel'),
              '#op' => 'cancel',
              '#limit_validation_errors' => [],
            ],
        ],
      ];
      $pane_row['#attributes']['class'][] = 'pane-configuration-editing';
    }
    else {
      $pane_row['configuration_summary'] = [];
      $pane_row['configuration_edit'] = [];

      $summary = $pane->getConfigurationSummary();
      if (!empty($summary)) {
        $pane_row['configuration_summary'] = [
          '#type' => 'inline_template',
          '#template' => '<div class="pane-configuration-summary">{{ summary|safe_join("<br />") }}</div>',
          '#context' => ['summary' => $summary],
          '#cell_attributes' => [
            'class' => ['pane-configuration-summary-cell']
          ],
        ];
      }
      // Check selected plugin settings to display edit link or not.
      $settings_form = $pane->buildConfigurationForm([], $form_state);
      if (!empty($settings_form)) {
        $pane_row['configuration_edit'] = $base_button + [
          '#type' => 'image_button',
          '#name' => $pane_id . '_configuration_edit',
          '#src' => 'core/misc/icons/787878/cog.svg',
          '#attributes' => ['class' => ['pane-configuration-edit'], 'alt' => $this->t('Edit')],
          '#op' => 'edit',
          '#limit_validation_errors' => [],
          '#prefix' => '<div class="pane-configuration-edit-wrapper">',
          '#suffix' => '</div>',
        ];
      }
    }

    return $pane_row;
  }

  /**
   * Form submission handler for multistep buttons.
   */
  public static function multistepSubmit($form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $op = $trigger['#op'];

    switch ($op) {
      case 'edit':
        // Open the configuration form.
        $form_state->set('pane_configuration_edit', $trigger['#pane_id']);
        break;

      case 'update':
        // Close the configuration form and store the updated configuration
        // values in form state.
        $pane_id = $trigger['#pane_id'];
        $form_state->set('pane_configuration_edit', NULL);
        $form_state->set('pane_configuration_update', $pane_id);
        //$this->entity = $this->buildEntity($form, $form_state);
        break;

      case 'cancel':
        // Close the configuration form.
        $form_state->set('pane_configuration_edit', NULL);
        break;
    }

    $form_state->setRebuild();
  }

  /**
   * Ajax handler for multistep buttons.
   */
  public static function multistepAjax($form, FormStateInterface $form_state) {
    // $form is the parent config entity form, not the plugin form.
    return $form['configuration']['panes'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // If the main "Save" button was submitted while a field settings subform
    // was being edited, update the new incoming settings when rebuilding the
    // entity, just as if the subform's "Update" button had been submitted.
    if ($edit_field = $form_state->get('pane_configuration_edit')) {
      $form_state->set('pane_configuration_update', $edit_field);
    }

    $form_values = $form_state->getValues();
    foreach ($form_values['panes'] as $pane_values) {
      if ($pane_values['step'] == '_disabled') {
        //
      }
      else {
        // Update field settings only if the submit handler told us to.
        if ($form_state->get('pane_configuration_update') === $pane_id) {
          // Only store settings actually used by the selected plugin.
          $default_settings = $this->paneManager->getDefaultSettings($options['type']);
          $options['settings'] = isset($values['settings']['form']) ? array_intersect_key($values['settings']['form'], $default_settings) : [];
          $form_state->set('pane_configuration_update', NULL);
        }

        $options['step'] = $values['type'];
        $options['weight'] = $values['weight'];
      }
    }
  }

}

