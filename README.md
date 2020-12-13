CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers
 * TODO


INTRODUCTION
------------

GitHub Auth adds a button on your login form to allow users to login or register
to Drupal site using their GitHub (https://github.com) account.

If an account with the same email already exist, it offers to merge accounts.

In an account with the same username already exist, it allows to choose a new
name to register.

*I developed it for my own site Coders.cat (https://coders.cat) because none of
existing Drupal modules fit into my custom requeriments at the time of writting
it.*


REQUIREMENTS
------------

This module requires the following module:

 * External Authentication (https://www.drupal.org/project/externalauth)


INSTALLATION
------------

Add new VCS repository (https://getcomposer.org/doc/05-repositories.md#vcs)
to your composer.json:

    "repositories": [
         {
             "type": "vcs",
             "url": "https://github.com/coders-cat/github_auth"
         }
     ],

Install with Composer: `$ composer require 'coders-cat/github_auth:^1.0'`


CONFIGURATION
-------------

1. Create a new GitHub OAut App
   (https://docs.github.com/en/free-pro-team@latest/developers/apps/creating-an-oauth-app)
2. Configure the module at Administration > Configuration > People > GitHub OAuth
   setting the Client ID and Client Secret of the App you just created.


MAINTAINERS
-----------

 * mikcat - https://github.com/mikcat

Supporting organization:

 * Coders.cat - https://coders.cat


TODO
----

* Provide some tests...
* Add to Drupal?
* Or... add to packagist?

