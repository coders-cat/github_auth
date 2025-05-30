<?php

namespace Drupal\github_auth;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\user\UserInterface;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use stdClass;
use function count;
use function GuzzleHttp\json_decode;
use function parse_str;
use function reset;
use function watchdog_exception;

final class GitHubAuthService {

  private readonly PrivateTempStore $tempStore;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CsrfTokenGenerator $csrfTokenGenerator,
    private readonly PrivateTempStoreFactory $tempStoreFactory,
    private readonly ClientInterface $httpClient,
    private readonly ExternalAuthInterface $externalAuth,
  ) {
    $this->tempStore = $this->tempStoreFactory->get('github_auth');
  }

  public function getGitHubAuthorizeUrl(): Url {
    $config = $this->configFactory->get('github_auth.oauthsettings');
    $redirect_uri = Url::fromRoute('github_auth.callback', [], ['absolute' => TRUE])->toString(true)->getGeneratedUrl();

    return Url::fromUri('https://github.com/login/oauth/authorize', [
        'absolute' => TRUE,
        'query' => [
          'client_id' => $config->get('client_id'),
          'scope' => 'user:email',
          'redirect_uri' => $redirect_uri,
          'state' => $this->csrfTokenGenerator->get('github_auth')
        ]
    ]);
  }

  public function verifyCsrfToken($token): bool {
    return $this->csrfTokenGenerator->validate($token, 'github_auth');
  }

  public function getAccessToken($code, $state): string {
    $config = $this->configFactory->get('github_auth.oauthsettings');

    $url = Url::fromUri('https://github.com/login/oauth/access_token', [
      'absolute' => TRUE,
      'query' => [
        'client_id' => $config->get('client_id'),
        'client_secret' => $config->get('client_secret'),
        'code' => $code,
        'state' => $state
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

  public function getGitHubUser($access_token): ?stdClass {
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
      watchdog_exception('github_auth', $ex, 'Error get github user: @message | Access token: @access_token', [
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
      watchdog_exception('github_auth', $ex, 'Error get github user emails: @message | Access token: @access_token', [
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

  public function keepGitHubUser($githubUser): void {
    $this->tempStore->set('githubuser', $githubUser);
  }

  public function getKeepedGitHubUser(): ?stdClass {
    return $this->tempStore->get('githubuser');
  }

  public function mergeAccountsByMailAndLogin(): UserInterface|bool {
    $githubUser = $this->getKeepedGitHubUser();
    if (!$githubUser) {
      $this->logger->error('Trying to merge account by mail without github user');
      return false;
    }

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $githubUser->email]);
    if (!$users || count($users) > 1) {
      $this->logger->error('Trying to merge account by mail without drupal account');
      return false;
    }

    $account = reset($users);
    $this->externalAuth->linkExistingAccount($githubUser->login, 'github_auth', $account);

    return $this->loginOrRegister($githubUser);
  }

  public function isUsernameAvailable(string $username): bool {
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]);
    return !$users;
  }

  public function loginOrRegister($githubUser, ?string $name = null) {
    $this->tempStore->delete('githubuser'); // Temp data cleanup
    return $this->externalAuth->loginRegister($githubUser->login, 'github_auth', [
        'name' => $name ?? $githubUser->login,
        'mail' => $githubUser->email
    ]);
  }

  public function externalUserExist($githubUser): UserInterface|bool {
    return $this->externalAuth->load($githubUser->login, 'github_auth');
  }
}
