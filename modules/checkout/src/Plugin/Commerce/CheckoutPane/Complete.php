<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the checkout complete message.
 *
 * @CommerceCheckoutPane(
 *   id = "complete_message",
 *   label = "Completion message",
 *   default_step = "complete",
 * )
 */
class Complete extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state) {
    $pane_form = [
      '#markup' => $this->t('You did it!'),
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state) {
    // ?!
  }

}
