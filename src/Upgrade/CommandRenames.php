<?php

namespace DrupalEnv\Upgrade;

/**
 * Every 2.x command name mapped to its 3.0 name.
 */
final class CommandRenames
{
    public const MAP = [
        'common:drush' => 'denv:drush',
        'common:drush-path' => 'denv:drush-path',
        'common:composer' => 'denv:composer',
        'common:site-install' => 'denv:site-install',
        'common:site-update' => 'denv:site-update',
        'common:remove-settings-php-changes' => 'denv:remove-settings-php-changes',
        'common:shortcuts-help' => 'denv:shortcuts-help',
        'common:shortcuts-aliases' => 'denv:shortcuts-aliases',
        'ddev:init' => 'denv:local-init',
        'lando:init' => 'denv:local-init',
        'ddev:reqs' => 'denv:local-reqs',
        'lando:reqs' => 'denv:local-reqs',
        'common:switch-local-env' => 'denv:local-switch',
        'ddev:duplicate-project' => 'denv:local-duplicate',
        'lando:duplicate-project' => 'denv:local-duplicate',
        'lando:setup-urls' => 'denv:local-urls',
        'lando:set-personal-services' => 'denv:local-personal-services',
        'ddev:xdebug-toggle-on-by-default' => 'denv:xdebug-toggle-on-by-default',
        'lando:xdebug-toggle-on-by-default' => 'denv:xdebug-toggle-on-by-default',
        'ddev:xdebug-toggle-drush' => 'denv:xdebug-toggle-drush',
        'lando:xdebug-toggle-always-connect' => 'denv:xdebug-toggle-always-connect',
        'xdebug:phpstorm-debug-config' => 'denv:xdebug-phpstorm-config',
        'common-admin:init' => 'denv-admin:init',
        'common-admin:local' => 'denv-admin:local',
        'ddev-admin:init' => 'denv-admin:local-setup',
        'lando-admin:init' => 'denv-admin:local-setup',
        'ddev-admin:config' => 'denv-admin:local-config',
        'lando-admin:set-project-name' => 'denv-admin:local-config',
        'lando-admin:set-recipe' => 'denv-admin:local-config',
        'lando-admin:set-php' => 'denv-admin:local-config',
        'ddev-admin:set-required-shared-services' => 'denv-admin:local-services-required',
        'lando-admin:set-required-shared-services' => 'denv-admin:local-services-required',
        'ddev-admin:set-optional-shared-services' => 'denv-admin:local-services-optional',
        'lando-admin:set-optional-shared-services' => 'denv-admin:local-services-optional',
        'ddev-admin:solr-config' => 'denv-admin:solr-config',
        'lando-admin:solr-config' => 'denv-admin:solr-config',
        'common-admin:remote' => 'denv-admin:remote',
        'common-admin:post-local-started' => 'denv-admin:post-local-started',
        'common-admin:optional-dependencies' => 'denv-admin:optional-dependencies',
        'common-admin:theme-set' => 'denv-admin:theme-set',
    ];
}
