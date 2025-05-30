<?php

namespace Drupal\github_auth\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\github_auth\GitHubAuthService;
use Override;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class UsernameChooseForm extends FormBase {

  private readonly stdClass $githubUser;

  public function __construct(
    private readonly GitHubAuthService $githubAuthManager,
  ) {
    $this->githubUser = $this->githubAuthManager->getKeepedGitHubUser();
  }

  #[Override]
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('github_auth.manager'),
    );
  }

  #[Override]
  public function getFormId() {
    return 'username_choose_form';
  }

  #[Override]
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->githubUser) {
      $title = $this->t('The username %value is already taken. Please choose another one.', [
        '%value' => $this->githubUser->login
      ]);

      $form['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $title,
      ];

      $form['new_username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('New username'),
        '#description' => $this->t('Choose another username'),
        '#maxlength' => 64,
        '#size' => 64,
        '#weight' => '0',
        '#required' => TRUE,
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ];
    }
    else {
      $form['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Missing GitHub user'),
      ];
    }

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromRoute('user.login')
    ];

    return $form;
  }

  #[Override]
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if (!$this->githubUser) {
      $form_state->setErrorByName('form_id', $this->t('Missing GitHub user'));
    }
    else {
      $username = $form_state->getValue('new_username');
      if (!$this->githubAuthManager->isUsernameAvailable($username)) {
        $form_state->setErrorByName('new_username', $this->t('The username %value is already taken.', [
            '%value' => $username
        ]));
      }
    }
  }

  #[Override]
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $account = $this->githubAuthManager->loginOrRegister($this->githubUser, $form_state->getValue('new_username'));
    $form_state->setRedirect('entity.user.canonical', [
      'user' => $account->id()
    ]);
  }
}
