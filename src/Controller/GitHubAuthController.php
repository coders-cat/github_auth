<?php

namespace Drupal\github_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\github_auth\GitHubAuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GitHubAuthController.
 */
class GitHubAuthController extends ControllerBase {

  /**
   * Drupal\github_auth\GitHubAuthService definition.
   *
   * @var GitHubAuthService
   */
  protected $githubAuthManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->githubAuthManager = $container->get('github_auth.manager');
    return $instance;
  }

  /**
   * Redirect to github.
   *
   * @return TrustedRedirectResponse
   *   Redirect to external url.
   */
  public function authorize(Request $request): TrustedRedirectResponse {
    if ($this->currentUser()->isAnonymous()) {
      // Forcem que la sessió del anònim sigui persistent per poder validar el csrfToken al callback
      // @TODO Hi ha alguna manera més neta de fer això ???
      $request->getSession()->set('github_auth_ensure_session', 1);
    }

    $url = $this->githubAuthManager->getGitHubAuthorizeUrl();
    return new TrustedRedirectResponse($url->toString());
  }

  /**
   * Callback.
   *
   * @return RedirectResponse
   */
  public function callback(Request $request): RedirectResponse {
    $code = $request->query->get('code');
    $state = $request->query->get('state');

    if (!$this->githubAuthManager->verifyCsrfToken($state)) {
      $this->getLogger('github_auth')->notice('Invalid state token @token', ['@token' => $state]);
      return $this->loginFailed();
    }

    $access_token = $this->githubAuthManager->getAccessToken($code, $state);
    if (!$access_token) {
      $this->getLogger('github_auth')->notice('Error getting GitHub access token @code @state', [
        '@code' => $code,
        '@state' => $state
      ]);
      return $this->loginFailed();
    }

    $githubUser = $this->githubAuthManager->getGitHubUserWithEmail($access_token);
    if (!$githubUser) {
      $this->getLogger('github_auth')->notice('Error getting GitHub user data @access_token', ['$access_token' => $access_token]);
      return $this->loginFailed();
    }

    if (!$githubUser->email) {
      $this->getLogger('github_auth')->notice('GitHub account does not have any primary verified email.');
      return $this->loginFailed();
    }

    // Verifiquem que no existeixi cap usuari amb el mateix login o email...
    if (!$this->githubAuthManager->externalUserExist($githubUser)) {
      $userStorage = $this->entityTypeManager()->getStorage('user');

      $userByEmail = $userStorage->loadByProperties(['mail' => $githubUser->email]);
      if ($userByEmail) {
        $this->githubAuthManager->keepGitHubUser($githubUser);
        return $this->redirect('github_auth.confirm_merge_accounts');
      }

      $userByName = $userStorage->loadByProperties(['name' => $githubUser->login]);
      if ($userByName) {
        $this->githubAuthManager->keepGitHubUser($githubUser);
        return $this->redirect('github_auth.username_choose_form');
      }
    }

    $account = $this->githubAuthManager->loginOrRegister($githubUser);
    if (!$account) {
      $this->getLogger('github_auth')->notice('GitHub external loginOrRegister failed for @login.', ['@login', $githubUser->login]);
      return $this->loginFailed();
    }

    return $this->redirect('entity.user.canonical', ['user' => $account->id()]);
  }

  private function loginFailed(): RedirectResponse {
    // Generic message to prevent guessing
    $this->messenger()->addError($this->t('Login with GitHub failed.'));
    return $this->redirect('user.login');
  }

}
