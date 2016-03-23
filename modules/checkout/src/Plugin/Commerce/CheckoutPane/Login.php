<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\Form\UserLoginForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the login or continue pane.
 *
 * @CommerceCheckoutPane(
 *   id = "login",
 *   label = "Login or continue as guest",
 *   default_step = "login",
 * )
 */
class Login extends CheckoutPaneBase implements CheckoutPaneInterface, ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new Login object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $account) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'allow_guest_checkout' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['allow_guest_checkout'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow guest checkout'),
      '#default_value' => $this->configuration['allow_guest_checkout'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $this->configuration['allow_guest_checkout'] = $form_state->getValue('allow_guest_checkout');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationSummary() {
    $summary = [];
    if (!empty($this->configuration['allow_guest_checkout'])) {
      $summary[] = $this->t('Guest checkout: Allowed');
    }
    else {
      $summary[] = $this->t('Guest checkout: Not allowed');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(OrderInterface $order) {
    return $this->currentUser->isAnonymous();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_state->get('order');

    $pane_form['guest'] = [
      '#type' => 'fieldset',
      'email' => [
        '#type' => 'email',
        '#title' => 'Email address',
        '#description' => $this->t('We will email your order confirmation to this address'),
        '#default_value' => $order->getEmail(),
      ],
    ];

    $pane_form['existing'] = [
      '#type' => 'fieldset',
    ];

    // Display login form:
    $pane_form['existing']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#size' => 60,
      '#maxlength' => USERNAME_MAX_LENGTH,
      '#required' => FALSE,
      '#attributes' => [
        'autocorrect' => 'none',
        'autocapitalize' => 'none',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ],
    ];

    $pane_form['existing']['pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#size' => 60,
      '#description' => $this->t('Enter the password that accompanies your username.'),
      '#required' => FALSE,
    ];

    return $pane_form;

  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state) {
    parent::validatePaneForm($pane_form, $form_state);

    $username = $form_state->getValue('name');
    $password = $form_state->getValue('pass');
    if (!empty($username) || !empty($password)) {
      /** @var \Drupal\Core\DependencyInjection\ClassResolverInterface $resolver */
      $resolver = \Drupal::service('class_resolver');
      $user_login_form = $resolver->getInstanceFromDefinition(UserLoginForm::class);
      $user_login_form->validateName($pane_form, $form_state);
      $user_login_form->validateAuthentication($pane_form, $form_state);
      $user_login_form->validateFinal($pane_form, $form_state);
    }
    else {
      // @todo inject email validator service, validate guest email.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_state->get('order');

    if (!empty($form_state->get('uid'))) {
      $account = User::load($form_state->get('uid'));
      user_login_finalize($account);
      $order->setOwner($account);
    }
    else {
      $order->setEmail($form_state->getValue('email'));
    }
  }

}
