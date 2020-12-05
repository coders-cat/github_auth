<?php

namespace Drupal\github_auth\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\github_auth\GitHubAuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MergeAccountsConfigForm.
 */
class MergeAccountsConfigForm extends ConfirmFormBase {

  /**
   * Drupal\github_auth\GitHubAuthService definition.
   *
   * @var GitHubAuthService
   */
  protected $githubAuthManager;
  protected $githubUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->githubAuthManager = $container->get('github_auth.manager');
    $instance->githubUser = $instance->githubAuthManager->getKeepedGitHubUser();
    return $instance;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->githubUser) {
      $form['#title'] = $this->t('There is nothing to merge.');

      $form['#attributes']['class'][] = 'confirmation';

      $form['actions']['cancel'] = ConfirmFormHelper::buildCancelLink($this, $this->getRequest());

      // By default, render the form using theme_confirm_form().
      if (!isset($form['#theme'])) {
        $form['#theme'] = 'confirm_form';
      }
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'merge_accounts_config_form';
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('user.login');
  }

  public function getQuestion(): TranslatableMarkup {
    return $this->t('An account with email "@email" already exist. Do you want to merge accounts?', [
        '@email' => $this->githubUser->email
    ]);
  }

  public function getConfirmText() {
    return $this->t('Merge');
  }

  public function getDescription() {
    return parent::getDescription();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->githubAuthManager->mergeAccountsByMailAndLogin()) {
      $account = $this->githubAuthManager->loginOrRegister($this->githubUser);
      $this->messenger()->addMessage($this->t('Accounts merged'));
      $form_state->setRedirect('entity.user.canonical', [
        'user' => $account->id()
      ]);
    }
    else {
      $this->messenger()->addError($this->t('Error merging accounts'));
      $form_state->setRedirect('user.login');
    }
  }

}
