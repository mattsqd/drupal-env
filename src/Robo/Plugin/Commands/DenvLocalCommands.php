<?php

namespace DrupalEnv\Robo\Plugin\Commands;

use DrupalEnv\Environment\Feature\SupportsDuplicate;
use DrupalEnv\Environment\Feature\SupportsPersonalServices;
use DrupalEnv\Environment\Feature\SupportsUrls;
use DrupalEnv\Environment\Feature\SupportsXdebugAlwaysConnect;
use DrupalEnv\Environment\Feature\SupportsXdebugDrush;
use DrupalEnv\Environment\Feature\SupportsXdebugOnByDefault;
use DrupalEnv\Robo\DenvTrait;
use Robo\ResultData;
use Robo\Tasks;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commands that act on the local environment, written once for all of them.
 *
 * @class RoboFile
 */
class DenvLocalCommands extends Tasks
{
    use DenvTrait;

    /**
     * Initialize the local environment, start it and install Drupal.
     *
     * @command denv:local-init
     *
     * @option env The local environment to use.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function localInit(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        if (!$this->runStep('denv:local-reqs')) {
            throw new \Exception('Unable to find all requirements. Please re-run this command after installing');
        }
        $env = $this->localEnv();
        // This will now be the active local environment, as many can be
        // installed. This allows the shortcuts like drush.sh to know which
        // environment to use.
        $this->setActiveEnvironment($env::getName());
        // Introduce the common shortcuts so one knows how they work and to
        // configure them.
        $this->introduceCommonShortcuts($io);
        // Capture the original install directory so copied projects can use
        // the original directory + suffix.
        $this->ensureOriginalInstallDirectorySet();
        $this->runOptionalStep($io, SupportsUrls::class, 'denv:local-urls', 'URL setup');
        $env->rebuild($io);
        if (!$this->runStep('denv:site-install')) {
            throw new \Exception('Unable to install the Drupal site.');
        }
        $io->success('Your environment has been started and Drupal site installed! Please use the one time login link to login.');
        $io->info('What\'s different?');
        $io->info('You have two new robo "tooling" available: "./robo.sh si" will install a Drupal site from configuration. "./robo.sh su" will update an already installed site, like a normal production deployment without destroying the database.');
        $io->info('You now have access to helper commands via ./robo.sh. These can be found under the "denv" and "denv-admin" groups. Those in "denv-admin" will effect files that are committed and therefore all developers. You also have access to the common shortcuts. You can find and reset their paths via "./robo.sh denv:shortcuts-help".');
        $env->afterInit($io);
        if ($io->confirm('Would you like to reset or learn more about shortcuts or reset their paths?', false)) {
            $this->_exec('./robo.sh denv:shortcuts-help');
        }
    }

    /**
     * Check that the requirements of the local environment are installed.
     *
     * @command denv:local-reqs
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData
     */
    public function localReqs(SymfonyStyle $io, array $opts = ['env' => null]): ResultData
    {
        return new ResultData($this->localEnv()->reqs($io) ? 0 : 1);
    }

    /**
     * Prompt to switch your local environment.
     *
     * @description Switch between DDEV, Lando, etc.
     *
     * @command denv:local-switch
     *
     * @return void
     *
     * @throws \Exception
     */
    public function localSwitch(SymfonyStyle $io): void
    {
        $current_env_name = $this->getActiveEnvironmentName();
        if ($current_env_name === null) {
            throw new \Exception('No local environment is set yet.');
        }
        $io->note("Your current local environment is $current_env_name");
        $names = $this->environmentRegistry()->names();
        if (count($names) < 2) {
            throw new \Exception('Only one local environment is available. Please run ./robo.sh denv-admin:local to install another.');
        }
        $options = [];
        foreach ($names as $name) {
            if ($name !== $current_env_name) {
                $options[$name] = $name;
            }
        }
        $options[''] = 'Cancel';
        $io->warning('Switching local environments will destroy your database, export your database if it is important.');
        $new_env_name = $io->choice('Which local environment would you like to switch to?', $options);
        if (strlen($new_env_name)) {
            $this->_exec('vendor/bin/robo denv:local-init --env=' . escapeshellarg($new_env_name));
        }
    }

    /**
     * Duplicate this project so it can run alongside the original.
     *
     * @command denv:local-duplicate
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData|null
     */
    public function localDuplicate(SymfonyStyle $io, array $opts = ['env' => null]): ?ResultData
    {
        $env = $this->requireFeature($io, SupportsDuplicate::class, 'denv:local-duplicate');
        if ($env === null) {
            return new ResultData(1);
        }
        $env->duplicate($io);
        return null;
    }

    /**
     * Set up the URLs of the local environment.
     *
     * @command denv:local-urls
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData|null
     */
    public function localUrls(SymfonyStyle $io, array $opts = ['env' => null]): ?ResultData
    {
        $env = $this->requireFeature($io, SupportsUrls::class, 'denv:local-urls');
        if ($env === null) {
            return new ResultData(1);
        }
        return $env->urls($io) ? null : new ResultData(1);
    }

    /**
     * Set your personal services for the local environment.
     *
     * @command denv:local-personal-services
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData|null
     */
    public function localPersonalServices(SymfonyStyle $io, array $opts = ['env' => null]): ?ResultData
    {
        $env = $this->requireFeature($io, SupportsPersonalServices::class, 'denv:local-personal-services');
        if ($env === null) {
            return new ResultData(1);
        }
        $env->personalServices($io);
        return null;
    }

    /**
     * Toggle whether Xdebug is on by default.
     *
     * @command denv:xdebug-toggle-on-by-default
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData|null
     */
    public function xdebugToggleOnByDefault(SymfonyStyle $io, array $opts = ['env' => null]): ?ResultData
    {
        $env = $this->requireFeature($io, SupportsXdebugOnByDefault::class, 'denv:xdebug-toggle-on-by-default');
        if ($env === null) {
            return new ResultData(1);
        }
        $env->xdebugToggleOnByDefault($io);
        return null;
    }

    /**
     * Toggle Xdebug for Drush.
     *
     * @command denv:xdebug-toggle-drush
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData|null
     */
    public function xdebugToggleDrush(SymfonyStyle $io, array $opts = ['env' => null]): ?ResultData
    {
        $env = $this->requireFeature($io, SupportsXdebugDrush::class, 'denv:xdebug-toggle-drush');
        if ($env === null) {
            return new ResultData(1);
        }
        $env->xdebugToggleDrush($io);
        return null;
    }

    /**
     * Toggle whether Xdebug always connects.
     *
     * @command denv:xdebug-toggle-always-connect
     *
     * @option env The local environment to use.
     *
     * @return \Robo\ResultData|null
     */
    public function xdebugToggleAlwaysConnect(SymfonyStyle $io, array $opts = ['env' => null]): ?ResultData
    {
        $env = $this->requireFeature($io, SupportsXdebugAlwaysConnect::class, 'denv:xdebug-toggle-always-connect');
        if ($env === null) {
            return new ResultData(1);
        }
        $env->xdebugToggleAlwaysConnect($io);
        return null;
    }

}
