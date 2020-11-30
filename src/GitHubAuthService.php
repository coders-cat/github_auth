<?php

namespace Drupal\github_auth;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Url;
use Drupal\externalauth\ExternalAuthInterface;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use function GuzzleHttp\json_decode;
use function parse_str;
use function watchdog_exception;

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
   * GuzzleHttp\ClientInterface definition.
   *
   * @var ClientInterface
   */
  protected $httpClient;

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
    ClientInterface $http_client,
    ExternalAuthInterface $externalauth_externalauth
  ) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->sessionManager = $session_manager;
    $this->csrfTokenGenerator = $csrf_token_generator;
    $this->currentUser = $current_user;
    $this->httpClient = $http_client;
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

  public function verifyCsrfToken($token) {
    return $this->csrfTokenGenerator->validate($token, 'github_auth');
  }

  public function getAccessToken($code, $state) {
    $config = $this->configFactory->get('github_auth.oauthsettings');

    $url = Url::fromUri('https://github.com/login/oauth/access_token', [
        'absolute' => TRUE,
        'query' => [
          'client_id' => $config->get('client_id'),
          'client_secret' => $config->get('client_secret'),
          'code' => $code,
          'state' => $state,
          'state' => $this->csrfTokenGenerator->get('github_auth')
        ]
    ]);

    try {
      $response = $this->httpClient->request('POST', $url->toString());
      if ($response->getStatusCode() === 200) {
        $body = (string) $response->getBody();
        $params = [];
        parse_str($body, $params);
        return $params['access_token'];
      }
      else {
        $this->logger->debug('getAccessToken response: @status - @phrase', [
          '@status' => $response->getStatusCode(),
          '@phrase' => $response->getReasonPhrase()
        ]);
      }
    }
    catch (Exception $ex) {
      watchdog_exception('github_auth', $ex, 'Error get access token: @message | Code: @code | State: @state', [
        '@message' => $ex->getMessage(),
        '@code' => $code,
        '@state' => $state
      ]);
    }

    return '';
  }

  public function getGitHubUser($access_token) {
    try {
      $response = $this->httpClient->request('GET', 'https://api.github.com/user', [
        RequestOptions::HEADERS => ['Authorization' => "token {$access_token}"]
      ]);
      if ($response->getStatusCode() === 200) {
        $body = (string) $response->getBody();
        return json_decode($body);
      }
    }
    catch (Exception $ex) {
      watchdog_exception('github_auth', $ex, 'Error get access token: @message | Access token: @access_token', [
        '@message' => $ex->getMessage(),
        '@access_token' => $access_token
      ]);
    }

    return null;
  }

  public function getGitHubUserEmails($access_token) {
    try {
      $response = $this->httpClient->request('GET', 'https://api.github.com/user/emails', [
        RequestOptions::HEADERS => ['Authorization' => "token {$access_token}"]
      ]);
      if ($response->getStatusCode() === 200) {
        $body = (string) $response->getBody();
        return json_decode($body);
      }
    }
    catch (Exception $ex) {
      watchdog_exception('github_auth', $ex, 'Error get access token: @message | Access token: @access_token', [
        '@message' => $ex->getMessage(),
        '@access_token' => $access_token
      ]);
    }

    return null;
  }

  public function getGitHubUserWithEmail($access_token) {
    $githubUser = $this->getGitHubUser($access_token);
    if ($githubUser) {
      $emails = $this->getGitHubUserEmails($access_token);
      foreach ($emails as $entry) {
        if ($entry->primary && $entry->verified) {
          $githubUser->email = $entry->email;
          break;
        }
      }
    }
    return $githubUser;
  }

  public function loginOrRegister($githubUser) {
    $account = $this->externalAuth->login($githubUser->login, 'github_auth');
    if (!$account) {

    }
    return $this->externalAuth->loginRegister($githubUser->login, 'github_auth', [
        'name' => $githubUser->login,
        'mail' => $githubUser->email
    ]);
  }

  public function externalUserExist($githubUser) {
    return $this->externalAuth->load($githubUser->login, 'github_auth');
  }

}
