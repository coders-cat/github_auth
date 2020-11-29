<?php

namespace Drupal\github_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\github_auth\GitHubAuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public function callback() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: callback')
    ];
  }

}
