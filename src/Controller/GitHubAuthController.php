<?php

namespace Drupal\github_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
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
   * Redirecttogithub.
   *
   * @return string
   *   Return Hello string.
   */
  public function authorize() {
    $url = $this->githubAuthManager->getGitHubAuthorizeUrl();
    return new TrustedRedirectResponse($url->toString());
  }

  /**
   * Callback.
   *
   * @return string
   *   Return Hello string.
   */
  public function callback(Request $request) {
    $code = $request->query->get('code');
    $state = $request->query->get('state');

    $access_token = $this->githubAuthManager->getAccessToken($code, $state);
    if (!$access_token) {
      $this->messenger()->addError($this->t('Error getting GitHub access token'));
      return new RedirectResponse('/user/login');
    }

    $githubUser = $this->githubAuthManager->getGitHubUserWithEmail($access_token);
    if (!$githubUser) {
      $this->messenger()->addError($this->t('Error getting GitHub user data'));
      return new RedirectResponse('/user/login');
    }

    if (!$githubUser->email) {
      $this->messenger()->addError($this->t('GitHub account does not have any primary verified email.'));
      return new RedirectResponse('/user/login');
    }

    // Verifiquem que no existeixi cap usuari amb el mateix login o email...
    // @TODO Merge accounts
    if (!$this->githubAuthManager->externalUserExist($githubUser)) {
      $userStorage = $this->entityTypeManager()->getStorage('user');
      $userByName = $userStorage->loadByProperties(['name' => $githubUser->login]);
      if ($userByName) {
        $this->messenger()->addError($this->t('The username %value is already taken.', [
            '%value' => $githubUser->login
        ]));
      }
      $userByEmail = $userStorage->loadByProperties(['mail' => $githubUser->email]);
      if ($userByEmail) {
        $this->githubAuthManager->keepGitHubUser($githubUser);
        $merge_confirm_url = Url::fromRoute('github_auth.confirm_merge_accounts');
        return new RedirectResponse($merge_confirm_url->toString());
      }
      if ($userByName || $userByEmail) {
        $this->messenger()->addError($this->t('Merge accounts are not supported yet.'));
        return new RedirectResponse('/user/login');
      }
    }

    $account = $this->githubAuthManager->loginOrRegister($githubUser);
    if (!$account) {
      $this->messenger()->addError($this->t('Error login with GitHub'));
      return new RedirectResponse('/user/login');
    }

    return new RedirectResponse('/user');
  }

}
