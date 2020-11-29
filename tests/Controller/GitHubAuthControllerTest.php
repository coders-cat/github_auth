<?php

namespace Drupal\github_auth\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\github_auth\GitHubAuthService;

/**
 * Provides automated tests for the github_auth module.
 */
class GitHubAuthControllerTest extends WebTestBase {

  /**
   * Drupal\github_auth\GitHubAuthService definition.
   *
   * @var \Drupal\github_auth\GitHubAuthService
   */
  protected $githubAuthManager;


  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "github_auth GitHubAuthController's controller functionality",
      'description' => 'Test Unit for module github_auth and controller GitHubAuthController.',
      'group' => 'Other',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests github_auth functionality.
   */
  public function testGitHubAuthController() {
    // Check that the basic functions of module github_auth.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
