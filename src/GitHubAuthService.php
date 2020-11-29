<?php

namespace Drupal\github_auth;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Url;
use Drupal\externalauth\ExternalAuthInterface;
use Psr\Log\LoggerInterface;

/**
 * Class GitHubAuthService.
 */
class GitHubAuthService {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   *
   * @var SessionManagerInterface
   */
  protected $sessionManager;

  /**
   *
   * @var CsrfTokenGenerator
   */
  protected $csrfTokenGenerator;

  /**
   * The current user service.
   *
   * @var AccountInterface
   */
  protected $currentUser;

  /**
   * Drupal\externalauth\ExternalAuthInterface definition.
   *
   * @var ExternalAuthInterface
   */
  protected $externalAuth;

  /**
   * Constructs a new GitHubAuthService object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger,
    SessionManagerInterface $session_manager,
    CsrfTokenGenerator $csrf_token_generator,
    AccountInterface $current_user,
    ExternalAuthInterface $externalauth_externalauth
  ) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->sessionManager = $session_manager;
    $this->csrfTokenGenerator = $csrf_token_generator;
    $this->currentUser = $current_user;
    $this->externalAuth = $externalauth_externalauth;
  }

  public function getGitHubAuthorizeUrl(): Url {
    if ($this->currentUser->isAnonymous() && !$this->sessionManager->isStarted()) {
      // ensure session for anonymous to use csrfToken
      $this->sessionManager->start();
    }

    $config = $this->configFactory->get('github_auth.oauthsettings');

    return Url::fromUri('https://github.com/login/oauth/authorize', [
        'absolute' => TRUE,
        'query' => [
          'client_id' => $config->get('client_id'),
          'scope' => 'user:email',
          'redirect_uri' => Url::fromRoute('github_auth.callback', [], ['absolute' => TRUE])->toString(true)->getGeneratedUrl(),
          'state' => $this->csrfTokenGenerator->get('github_auth')
        ]
    ]);
  }

}
