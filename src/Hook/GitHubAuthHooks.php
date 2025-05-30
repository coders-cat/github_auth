<?php

namespace Drupal\github_auth\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

final class GitHubAuthHooks {

  use StringTranslationTrait;

  #[Hook('form_user_login_form_alter')]
  public function formUserLoginFormAlter(array &$form): void {
    $form['github_auth'] = [
      '#type' => 'container',
      '#weight' => -2,
      '#attributes' => [
        'class' => ['github_auth__link'],
      ],
      'github_link' => [
        '#type' => 'link',
        '#title' => $this->t('Login with GitHub'),
        '#url' => Url::fromRoute('github_auth.authorize'),
      ]
    ];

    $form['separator'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Or log in with a username'),
      '#weight' => -1,
      '#attributes' => [
        'class' => ['github_auth__separator'],
      ]
    ];

    $form['#attached']['library'][] = 'github_auth/github_auth';
  }
}
