<?php

namespace DrupalEnv\Robo\Plugin\Commands;

use DrupalEnv\Environment\Feature\SupportsSolrConfig;
use DrupalEnv\Robo\DenvTrait;
use Robo\ResultData;
use Robo\Tasks;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commands that change committed files, so they affect every developer.
 *
 * @class RoboFile
 */
class DenvAdminCommands extends Tasks
{
    use DenvTrait;

    /**
     * Initialize the Drupal Environment.
     *
     * @command denv-admin:init
     *
     * @return void
     */
    public function init(SymfonyStyle $io): void
    {
        // Introduce the common shortcuts so one knows how they work and to
        // configure them.
        $this->introduceCommonShortcuts($io);

        $io->success('Your project is now ready to install remote (none yet) and local environments');

        $io->success('Configure one or more local environments: ./robo.sh denv-admin:local');
        if ($io->confirm('Would you like to install and configure a local environment?')) {
            $this->_exec('./robo.sh denv-admin:local');
        }
    }

    /**
     * Allows one to install one local environment at a time.
     *
     * @command denv-admin:local
     *
     * @return void
     */
    public function local(SymfonyStyle $io): void
    {
        $locals = [
            'lando' => [
                'name' => 'Lando',
                'installed' => $this->isDependencyInstalled('mattsqd/drupal-env-lando') ? 'Yes, installed' : 'Not installed',
                'description' => "https://lando.dev/ Push-button development environments hosted on your computer or in the cloud. Automate your developer workflow and share it with your team.",
                'package' => 'mattsqd/drupal-env-lando:^3.0',
            ],
            'ddev' => [
                'name' => 'DDEV',
                'installed' => $this->isDependencyInstalled('mattsqd/drupal-env-ddev') ? 'Yes, installed' : 'Not installed',
                'description' => 'https://ddev.com/ Docker-based PHP development environments. Container superpowers with zero required Docker skills: environments in minutes, multiple concurrent projects, and less time to deployment.',
                'package' => 'mattsqd/drupal-env-ddev:^3.0',
            ],
        ];
        $rows = [];
        foreach ($locals as $key => $options) {
            $post_install_commands = ["./robo.sh drupal-env-$key:enable-scaffold", "vendor/bin/robo denv-admin:local-setup --env=$key"];
            $rows[$key] = [
                $options['name'],
                $options['installed'],
                $options['package'],
                implode(', ', $post_install_commands),
                $options['description'],
            ];
        }
        $table = $io->createTable();
        $table->setHeaders(['Name', 'Installed', 'Package', 'Post Install Commands', 'Description']);
        $table->setRows($rows);
        $table->setColumnMaxWidth(3, 10);
        $table->setColumnMaxWidth(4, 25);
        $table->render();
        $not_installed = array_filter($locals, static function (string $key) use ($locals) {
            return $locals[$key]['installed'] === 'Not installed';
        }, ARRAY_FILTER_USE_KEY);
        if (empty($not_installed)) {
            $io->warning('You have installed all local environments.');
            return;
        }
        $options = array_combine(array_keys($not_installed), array_column($not_installed, 'name'));
        $options['cancel'] = 'Cancel';
        $choice = $io->choice('Which environment do you want to install?', $options, 'cancel');
        if ($choice === 'cancel') {
            $io->caution('Cancelled adding a new local environment.');
            return;
        }
        // Install the Drupal Env Local package.
        if ($this->installDependencies($io, false, [$locals[$choice]['package'] => $locals[$choice]['description']])) {
            $continued = $io->confirm('Success! Would you like to continue the installation and configuration of the new local environment');
            if ($continued) {
                $this->_exec("./robo.sh drupal-env-$choice:enable-scaffold");
                $this->_exec("vendor/bin/robo denv-admin:local-setup --env=$choice");
            }
            // The installed packages are cached for this process, so count the
            // one just installed.
            $installed_count = count($locals) - count($not_installed) + 1;
            if ($continued && $installed_count >= 2 && $this->getPreferredEnvironmentName() === null) {
                if ($io->confirm("Make {$locals[$choice]['name']} the preferred environment for new developers?", false)) {
                    $this->setPreferredEnvironmentName($choice);
                }
            }
        } else {
            $io->warning("There was an issue installing {$locals[$choice]['package']}.");
        }
    }

    /**
     * Configure the local environment, start it and install Drupal.
     *
     * @command denv-admin:local-setup
     *
     * @option env The local environment to use.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function localSetup(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $label = $this->localEnvLabel();
        if ($this->localEnv()->isSetUp() && !$io->confirm("$label is already set up, are you sure you want to update its configuration?", false)) {
            $io->note('Cancelled.');
            return;
        }
        $this->enterToContinue($io, "Running through the interactive configuration of $label. This can be run by itself later via `./robo.sh denv-admin:local-config`.");
        if (!$this->runStep('denv-admin:local-config')) {
            throw new \Exception('The configuration of the local environment failed.');
        }

        $this->enterToContinue($io, 'Setting required shared services. This can be run by itself later via `./robo.sh denv-admin:local-services-required`.');
        $this->runStep('denv-admin:local-services-required');

        $this->enterToContinue($io, "$label will now start up and install Drupal so that the scripts can work on your current install. This can be run by itself later via `./robo.sh denv:local-init`.");
        if (!$this->runStep('denv:local-init')) {
            throw new \Exception('The local environment failed to initialize.');
        }

        $this->enterToContinue($io, 'Setting optional shared services. This can be run by itself later via `./robo.sh denv-admin:local-services-optional`.');
        $this->runStep('denv-admin:local-services-optional');

        // The following are shared commands that are not specific to an
        // environment, but instead just need a local installed to work.
        $this->enterToContinue($io, 'Taking action after a local has been installed. This can be run by itself later via `./robo.sh denv-admin:post-local-started`.');
        $this->runStep('denv-admin:post-local-started');
    }

    /**
     * Interactively configure the local environment.
     *
     * @command denv-admin:local-config
     *
     * @option env The local environment to use.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function localConfig(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $this->localEnv()->config($io);
    }

    /**
     * Set the services every developer of the project must use.
     *
     * @command denv-admin:local-services-required
     *
     * @option env The local environment to use.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function localServicesRequired(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $this->localEnv()->servicesRequired($io);
    }

    /**
     * Set the services every developer of the project may opt into.
     *
     * @command denv-admin:local-services-optional
     *
     * @option env The local environment to use.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function localServicesOptional(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $this->localEnv()->servicesOptional($io);
    }

    /**
     * Set or clear the preferred local environment for new developers.
     *
     * @command denv-admin:local-preferred
     *
     * @return void
     */
    public function localPreferred(SymfonyStyle $io): void
    {
        $names = $this->environmentRegistry()->names();
        if (empty($names)) {
            $io->warning('No local environments are installed. Run ./robo.sh denv-admin:local to install one.');
            return;
        }
        $current = $this->getPreferredEnvironmentName();
        $options = array_combine($names, $names);
        $options['none'] = 'None (ask each developer)';
        $choice = $io->choice('Which local environment should new developers use?', $options, in_array($current, $names, true) ? $current : 'none');
        if ($choice === 'none') {
            $this->setPreferredEnvironmentName(null);
            $io->note('The preferred local environment has been cleared.');
            return;
        }
        $this->setPreferredEnvironmentName($choice);
        $io->success("The preferred local environment is now $choice.");
    }

    /**
     * Put the Solr configuration in place.
     *
     * @command denv-admin:solr-config
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData|null
     */
    public function solrConfig(SymfonyStyle $io, array $opts = ['env' => null]): ?ResultData
    {
        $env = $this->requireFeature($io, SupportsSolrConfig::class, 'denv-admin:solr-config');
        if ($env === null) {
            return new ResultData(1);
        }
        $env->solrConfig($io);
        return null;
    }

    /**
     * Allows one to install a remote environment.
     *
     * @command denv-admin:remote
     *
     * @return void
     */
    public function remote(SymfonyStyle $io): void
    {
        $io->caution('There are no remotes able to be configured at this time, Platform.sh is coming soon.');
    }

    /**
     * After a local is installed and started, run commands.
     *
     * @command denv-admin:post-local-started
     *
     * @option env The local environment to use.
     *
     * @return void
     */
    public function postLocalStarted(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $this->enterToContinue($io, 'Offering to install optional composer dependencies. Can be re-run with `./robo.sh denv-admin:optional-dependencies`.');
        $this->runStep('denv-admin:optional-dependencies');

        $this->enterToContinue($io, 'Offering to install and enable themes. Can be re-run with `./robo.sh denv-admin:theme-set`.');
        $this->runStep('denv-admin:theme-set');
    }

    /**
     * Called from each local & remote install.
     *
     * @command denv-admin:optional-dependencies
     *
     * @option env The local environment to use.
     *
     * @return void
     */
    public function optionalDependencies(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $flag_name = 'flags.common.installedOptionalDependenciesAlreadyRun';

        $already_run_label = '';
        if ($already_run = $this->getConfig($flag_name, 0)) {
            $already_run_label = " This has already been run for this project, but can be run until their are no modules to enable, if you'd like.";
        }
        if ($io->confirm("Would you like to install some optional but helpful dependencies?$already_run_label", !$already_run)) {
            $optional_deps = [
                'drupal/admin_toolbar' => 'Easy access at the top of the page to admin only links.',
                'drupal/paragraphs' => 'Allows site builders to create dynamic content for every entity.',
                'drupal/disable_user_1_edit' => 'Don\'t let anyone but user 1 edit the super user.',
                'drupal/menu_admin_per_menu' => 'Allows granular per-menu access.',
                'drupal/role_delegation' => 'Allow a role to give only certain roles (don\'t let them make admins)',
                'drupal/twig_tweak' => 'Handy shortcuts and helpers when working in Twig',
                'drupal/twig_field_value' => 'Easily get field values and labels separately in Twig.',
            ];
            $this->installDependencies($io, true, $optional_deps, false);
            $this->installDependencies($io, true, ['drupal/devel' => 'This has many great debugging tools.'], true, true);
        }

        if (!$already_run) {
            $this->saveConfig($flag_name, 1);
        }
    }

    /**
     * Choose your default and/or admin themes.
     *
     * @command denv-admin:theme-set
     *
     * @option env The local environment to use.
     *
     * @return void
     */
    public function themeSet(SymfonyStyle $io, array $opts = ['env' => null]): void {
        $this->isDrupalInstalled($io);
        foreach ([
                     'default' => [
                         'required',
                         "Would you like to set a default theme? Olivero provides some styling while Stark is a blank slate. The default theme will be used for both admin and non-admin pages if no admin theme is set.",
                     ],
                     'admin' => [
                         'not-required',
                         'Would you like to set an admin only theme? Claro is the recommended Admin theme. The admin theme will replace the default theme for admin pages only.',
                     ]] as $theme_type => $values) {
            $output = $this->drush($io, [
                'config-get',
                'system.theme',
                $theme_type
            ], ['print_output' => FALSE])->getOutputData();
            $default_theme = str_replace("'system.theme:$theme_type': ", '', $output);
            $io->note($values[1]);
            $io->info('Options are below for a theme (Use the machine name inside "()"');
            $this->drush($io, [
                'pm-list',
                '--type=theme',
            ])->getOutputData();
            $default_theme_option = '';
            $additional = '';
            if ($values[0] === 'required') {
                $default_theme_option = $default_theme;
            } else {
                $additional = " (Leave blank to not set a $theme_type theme)";
            }
            $theme_choice = $io->ask("Choose a $theme_type theme, current '$default_theme'$additional", $default_theme_option);
            if ($theme_choice === $default_theme || $default_theme === 'null' && $theme_choice === '') {
                $io->note('Leaving as is...');
                continue;
            }
            if (strlen($theme_choice)) {
                // Attempt to make it the machine name if they typed the display
                // name.
                $theme_choice = strtolower(str_replace(' ', '_', $theme_choice));
                // Stable 9 does not follow the normal form.
                if ($theme_choice === 'stable_9') {
                    $theme_choice = 'stable9';
                }
                $this->drush($io, ['theme:enable', $theme_choice]);
            } else {
                // This is supposed to work according to
                // https://github.com/drush-ops/drush/pull/4780 but it does not.
                // Luckily setting a bad value here will not cause the site to
                // break.
                $theme_choice = 'null';
            }
            // Set as the default $theme_type.
            $this->drush($io, ['config-set', 'system.theme', $theme_type, $theme_choice, '-y']);
            if ($theme_type === 'admin' && $theme_choice !== 'null') {
                $node_edit_choice = $io->confirm('Would you like to use the admin theme for node edit pages? Note that if one does not have the permission to view the admin theme, they will see the default theme.');
                $this->drush($io, ['config-set', 'node.settings', 'use_admin_theme', (int) $node_edit_choice, '-y']);
            }
        }
    }

}
