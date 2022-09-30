<?php

namespace Drupal\crm_department_balance\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\da_sales_calls_list\Ajax\RefreshViewCommand;
use Drupal\hr_common\Ajax\CloseFormCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RemoveStaffForm.
 */
class RemoveStaffForm extends FormBase {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service instance.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Drupal\crm_statements\PaymentStatementService.
   *
   * @var \Drupal\crm_statements\PaymentStatementService
   */
  protected $paymentStatementService;

  /**
   * Action Log Service.
   *
   * @var \Drupal\crm_action_log\ActionLogService
   */
  protected $actionLog;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    $instance->paymentStatementService = $container->get('crm_statements.statement_service');
    $instance->actionLog = $container->get('crm_action_log.log');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'remove_staff';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL, $uid = NULL) {
    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['user_id'] = [
      '#type' => 'hidden',
      '#value' => $uid,
    ];

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['help'] = [
      '#type' => 'item',
      '#title' => $this->t('Are you sure you want to delete this staff?'),
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'remove-btns',
        ],
      ],
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => 'Yes',
      "#attributes" => [
        'class' => ['btn', 'btn-success'],
      ],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#name' => 'cancel',
      '#value' => 'Cancel',
      "#attributes" => [
        'class' => ['btn', 'btn-danger'],
      ],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      '#suffix' => '</div>',
    ];

    // Attach the inline messages library.
    $form['#attached']['library'] = [
      'hr_common/close_popup',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigerring_element = $form_state->getTriggeringElement();
    if ($trigerring_element['#name'] !== 'cancel') {
      $department_balance = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('nid'));
      $staff_balance = $department_balance->get('db_staff')->getValue();
      $staff = [];
      foreach ($staff_balance as $staff_balance_value) {
        $staff[$staff_balance_value['target_id']] = $staff_balance_value['target_id'];
      }
      unset($staff[$form_state->getValue('user_id')]);
      $department_balance->set('db_staff', $staff);
      $department_balance->save();
      $log_message = $this->t('Staff were deleted from Department Balance - @name', [
        '@name' => $department_balance->get('db_balance_name')->value,
      ]);
      $this->actionLog->log('remove_staff_from_department_balance', $log_message, $this->currentUser->id());
      $this->messenger()->addStatus($this->t('Staff was successfully deleted'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $message = [
      '#theme' => 'status_messages',
      '#message_list' => $this->messenger()->all(),
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'error' => $this->t('Error message'),
        'warning' => $this->t('Warning message'),
      ],
    ];
    $messages = $this->renderer->render($message);

    // If errors exists return new form.
    $errors = $this->messenger()->messagesByType('error');
    $ajax_response->addCommand(new HtmlCommand('#form-system-messages', $messages));

    if (empty($errors)) {
      $ajax_response->addCommand(new CloseFormCommand(1000));
      $ajax_response->addCommand(new HtmlCommand('.hide-form', ''));
      $parent_url = getRequestParentUrl();
      if ($parent_url) {
        $ajax_response->addCommand(new RedirectCommand($parent_url));
      }
    }
    $this->messenger()->deleteAll();

    return $ajax_response;
  }

}
