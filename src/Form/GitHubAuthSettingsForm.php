<?php

namespace Drupal\github_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Override;

final class GitHubAuthSettingsForm extends ConfigFormBase {

  #[Override]
  protected function getEditableConfigNames() {
    return [
      'github_auth.oauthsettings',
    ];
  }

  #[Override]
  public function getFormId() {
    return 'github_oauth_settings_form';
  }

  #[Override]
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('github_auth.oauthsettings');

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#required' => TRUE,
      '#default_value' => $config->get('client_id'),
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client secret'),
      '#required' => TRUE,
      '#default_value' => $config->get('client_secret'),
    ];

    return parent::buildForm($form, $form_state);
  }

  #[Override]
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('github_auth.oauthsettings')
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->save();
  }
}
