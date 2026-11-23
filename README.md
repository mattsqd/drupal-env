# Drupal Env

A platform agnostic tool to develop Drupal websites locally. Integrates into an existing project or start from scratch. A wrapper around other tools, like Lando, to speed up the creation of a local environment and provide tooling.

Features:

* CLI commands to configure your environment with the version of tools you want (PHP version choice, Apache or Nginx, etc)
* CLI commands to provide optional personal developer tools (Phpmyadmin, Mailhog, etc)
* Shortcuts to tools, like `drush`, to interact with the current environment instead of needing to calling a third party application (`lando drush`, `ddev drush`)
* Create additional environments quickly to test or code review without destroying your development environment
* Ability to update to new versions of this tool by taking advantage of Drupal core scaffolding tools, all tools are committed to your environment and alterable, not stuck in the `vendor/` directory.

Learn more and get started by visiting the [Wiki](https://github.com/mattsqd/drupal-env/wiki).

## Customizing with hooks

Sites customize drupal-env only through hooks in `RoboFile.php`, which is scaffolded once and never overwritten. Hooks can check the active environment with `$this->localEnvName()`. Commands live in the `denv:` group (every developer) and the `denv-admin:` group (changes committed files, so affects everyone).

Run code before or after a command, for every environment:

```php
/**
 * @hook pre-command denv:local-init
 */
public function preLocalInit(\Consolidation\AnnotatedCommand\CommandData $commandData): void
{
}

/**
 * @hook post-command denv:local-init
 */
public function postLocalInit($result, \Consolidation\AnnotatedCommand\CommandData $commandData): void
{
}
```

Run code for one environment only:

```php
/**
 * @hook post-command denv:local-init
 */
public function postLocalInitDdev($result, \Consolidation\AnnotatedCommand\CommandData $commandData): void
{
    if ($this->localEnvName() === 'ddev') {
        $this->_exec('ddev describe');
    }
}
```

Replace a command for one environment, handling the others with the environment's own method (only the first registered replacement runs):

```php
/**
 * @hook replace-command denv:local-reqs
 */
public function replaceLocalReqs(\Symfony\Component\Console\Style\SymfonyStyle $io): \Robo\ResultData
{
    if ($this->localEnvName() === 'ddev') {
        return new \Robo\ResultData(0);
    }
    return new \Robo\ResultData($this->localEnv()->reqs($io) ? 0 : 1);
}
```

Environment-backed commands call these methods of the active environment (`DrupalEnv\Environment\Environment`) or of an optional feature interface it implements (`DrupalEnv\Environment\Feature`). If the environment lacks an optional feature, the command says so and exits non-zero, and flows such as `denv:local-init` skip that step.

| Command | Method | Feature |
|---|---|---|
| `denv:local-reqs` | `reqs()` | required |
| `denv:local-init` | `rebuild()`, then `afterInit()` | required |
| `denv-admin:local-setup` | `isSetUp()` | required |
| `denv-admin:local-config` | `config()` | required |
| `denv-admin:local-services-required` | `servicesRequired()` | required |
| `denv-admin:local-services-optional` | `servicesOptional()` | required |
| `denv:local-duplicate` | `duplicate()` | `SupportsDuplicate` |
| `denv:local-urls` | `urls()` | `SupportsUrls` |
| `denv:local-personal-services` | `personalServices()` | `SupportsPersonalServices` |
| `denv:xdebug-toggle-on-by-default` | `xdebugToggleOnByDefault()` | `SupportsXdebugOnByDefault` |
| `denv:xdebug-toggle-drush` | `xdebugToggleDrush()` | `SupportsXdebugDrush` |
| `denv:xdebug-toggle-always-connect` | `xdebugToggleAlwaysConnect()` | `SupportsXdebugAlwaysConnect` |
| `denv-admin:solr-config` | `solrConfig()` | `SupportsSolrConfig` |

Replacing a shared flow (such as `denv:local-init` or `denv-admin:local-setup`) or a command that doesn't depend on the environment (such as `denv:site-install`) replaces it for every environment at once.

## Upgrading from 2.x

3.0 changes how drupal-env is organized. In short:

- The commands moved into `vendor/`. The 2.x copies in `RoboEnv/Robo/Plugin/Commands/` (`CommonTrait.php`, `DdevCommands.php`, and so on) are no longer used.
- There is one command set, `denv:` and `denv-admin:`, for every environment. `common:site-update` is now `denv:site-update`, `ddev:init` and `lando:init` are now `denv:local-init`, and so on.
- Customize with hooks in `RoboFile.php` instead of editing copies of the commands.
- On the first `composer install` after upgrading, drupal-env checks your site. If it finds committed 2.x copies, code using the 2.x class names, or 2.x command names, it stops Composer, deletes nothing, and lists what to change. Fix the list and run `composer install` again to finish the cleanup.

The full upgrade guide, with the rename table, is in the release notes: https://github.com/mattsqd/drupal-env/releases/tag/3.0.0
