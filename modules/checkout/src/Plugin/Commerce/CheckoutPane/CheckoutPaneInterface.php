<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for checkout panes.
 * 
 * Checkout panes are configurable forms embedded into the checkout flow form.
 * 
 */
interface CheckoutPaneInterface extends ConfigurablePluginInterface, PluginFormInterface, PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Gets the pane id.
   *
   * @return string
   *   The pane id.
   */
  public function getId();

  /**
   * Gets the pane label.
   * 
   * @return string
   *   The pane label.
   */
  public function getLabel();

  /**
   * Gets the pane weight.
   *
   * @return string
   *   The pane weight.
   */
  public function getWeight();

  /**
   * Sets the pane weight.
   *
   * @param int $weight
   *   The pane weight.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Gets a short summary of the pane configuration.
   *
   * Complements the methods provided by PluginFormInterface, allowing
   * the checkout flow form to provide a summary of pane configuration.
   *
   * @return string[]
   *   A short summary of the pane configuration.
   */
  public function getConfigurationSummary();
  
  /**
   * Determines whether the pane is visible.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The current order.
   *
   * @return bool
   *   TRUE if the pane is visible, FALSE otherwise.
   */
  public function isVisible(OrderInterface $order);

  /**
   * Builds the pane form.
   *
   * @param array $pane_form
   *   The pane form, containing the following basic properties:
   *   - #parents: Identifies the position of the pane form in the overall
   *     parent form, and identifies the location where the field values are
   *     placed within $form_state->getValues().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state);

  /**
   * Validates the pane form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state);

  /**
   * Handles the submission of an pane form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state);

}
