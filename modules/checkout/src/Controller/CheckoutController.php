<?php

namespace Drupal\commerce_checkout\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the checkout form page.
 */
class CheckoutController implements ContainerInjectionInterface {

  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new CheckoutController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array
   *   The render form.
   */
  public function formPage(RouteMatchInterface $route_match) {
    $order = $route_match->getParameter('commerce_order');
    $checkout_flow = $this->getCheckoutFlow($order);
    $form_state = new FormState();
    return $this->formBuilder->buildForm($checkout_flow->getPlugin(), $form_state);
  }

  /**
   * Gets the order's checkout flow.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_checkout\Entity\CheckoutFlowInterface
   *   THe checkout flow.
   */
  protected function getCheckoutFlow(OrderInterface $order) {
    if ($order->checkout_flow->isEmpty()) {
      /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
      $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());
      $checkout_flow = $order_type->getThirdPartySetting('commerce_checkout', 'checkout_flow');
      // @todo Allow other modules to add their own resolving logic.
      $order->checkout_flow->target_id = $checkout_flow;
      $order->save();
    }

    return $order->checkout_flow->entity;
  }


}
