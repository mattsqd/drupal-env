# Drupal Env

A platform agnostic tool to develop Drupal websites locally. Integrates into an existing project or start from scratch. A wrapper around other tools, like Lando, to speed up the creation of a local environment and provide tooling.

Features:

* CLI commands to configure your environment with the version of tools you want (PHP version choice, Apache or Nginx, etc)
* CLI commands to provide optional personal developer tools (Phpmyadmin, Mailhog, etc)
* Shortcuts to tools, like `drush`, to interact with the current environment instead of needing to calling a third party application (`lando drush`, `ddev drush`)
* Create additional environments quickly to test or code review without destroying your development environment
* Ability to update to new versions of this tool by taking advantage of Drupal core scaffolding tools, all tools are committed to your environment and alterable, not stuck in the `vendor/` directory.

Learn more and get started by visiting the [Wiki](https://github.com/mattsqd/drupal-env/wiki).
