<?php

namespace DrupalEnv\Robo\Plugin\Commands;

use Robo\Tasks;
use DrupalEnvLando\Robo\Plugin\Commands\DrupalEnvLandoCommands;
use DrupalEnvLando\Robo\Plugin\Commands\DrupalEnvDdevCommands;

/**
 * Provide commands to handle installation tasks.
 *
 * @class RoboFile
 */
abstract class DrupalEnvCommandsBase extends Tasks
{

    /**
     * The name of this package.
     *
     * @var string
     */
    protected string $package_name;

    /**
     * If the command is scaffolding on behalf of another package, there is
     * no prescaffolding to run.
     *
     * @var bool
     */
    protected bool $disable_pre_scaffolding = false;

    /**
     * Retrieve the package name.
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getPackageName(): string
    {
        if (empty($this->package_name)) {
            throw new \Exception('$this->package_name must be set.');
        }
        return $this->package_name;
    }

    /**
     * Get the path to composer.
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getComposerPath(): string
    {
        /*if (`which ./composer.sh`) {
          return './composer.sh';
        } else*/
        if (`which composer`) {
            return 'composer';
        } elseif (`which docker`) {
            return 'docker run --rm -i --tty -v $PWD:/app composer:2';
        }
        throw new \Exception('Either composer or docker must be installed to continue');
    }

    /**
     * Scaffold a single project.
     *
     * This enables the scaffolding, runs it, then disables it from being
     * scaffolded when composer drupal:scaffold is called. Scaffolding is
     * manual process so that a proper order can be done.
     *
     * @command drupal-env:scaffold
     *
     * @param string $package_name
     *   Allows to scaffold packages that do not have their own Robo command.
     *   If this is past, then there is no preScaffoldChanges() that get run.
     */
    public function scaffold(string $package_name = ''): void
    {
        if (strlen($package_name)) {
            $this->package_name = $package_name;
            $this->disable_pre_scaffolding = true;
        }
        $this->updateScaffolding();
    }

    /**
     * Run the scaffolding for each project needs it.
     *
     * @command drupal-env:scaffold-all
     */
    public function scaffoldAll(): void
    {
        // Drupal core.
        $this->_exec('vendor/bin/robo drupal-env:scaffold drupal/core');
        // Common scaffold (this file).
        $this->_exec('vendor/bin/robo drupal-env:scaffold');
        // Scaffold all other drupal env projects if they exist.
        // Lando scaffold.
        // @todo: Determine if some sort of event system is possible for this.
        if (class_exists(DrupalEnvLandoCommands::class)) {
            $this->_exec('vendor/bin/robo drupal-env-lando:scaffold');
        }
        if (class_exists(DrupalEnvDdevCommands::class)) {
            $this->_exec('vendor/bin/robo drupal-env-ddev:scaffold');
        }
    }

    /**
     * Update all the scaffolding in the current project.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function updateScaffolding(): void
    {
        $composer_path = $this->getComposerPath();

        $composer_json_hash_before = md5_file('composer.json');

        // Take care of any items needed before scaffolding happens.
        if (!$this->disable_pre_scaffolding) {
            $this->preScaffoldChanges();
        }

        // Make sure that our scaffolding can run.
        $this->enableScaffolding();

        // Now that everything is ready, run the scaffolding.

        $this->_exec($composer_path . ' drupal:scaffold');

        $this->disableScaffolding();

        // If composer.json was updated, the lock file also has to be updated.
        if ($composer_json_hash_before !== md5_file('composer.json')) {
            $this->_exec($composer_path . ' update --lock');
        }

        $this->yell("The scaffolding has been enabled and run for {$this->getPackageName()}, bringing in any new scaffolded files. Afterwords, it was disabled so that scaffolding is not updated every time composer install is called.");
    }

    /**
     * Take care of any items needed before scaffolding happens.
     *
     * @return void
     */
    abstract protected function preScaffoldChanges(): void;

    /**
     * Retrieve the value of composer.json.
     *
     * @return array
     */
    protected function getComposerJson(): array
    {
        return json_decode(file_get_contents('composer.json'), true);
    }

    /**
     * Save composer.json.
     *
     * @param array $composer_json
     *
     * @return void
     */
    protected function saveComposerJson(array $composer_json): void
    {
        file_put_contents('composer.json', json_encode($composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    /**
     * Turn on scaffolding in composer.json for a single $project.
     *
     * @return bool
     *   True if composer.json needed to be updated.
     */
    protected function enableScaffolding(): bool
    {
        $composer_json = $this->getComposerJson();
        if (!in_array($this->package_name, $composer_json['extra']['drupal-scaffold']['allowed-packages'] ?? [])) {
            $composer_json['extra']['drupal-scaffold']['allowed-packages'][] = $this->package_name;
            $this->saveComposerJson($composer_json);
            //$this->_exec($composer_path . ' config extra.drupal-scaffold.allowed-packages --json --merge \'["mattsqd/drupal-env"]\'');
            return true;
        }
        return false;
    }

    /**
     * Turn off scaffolding in composer.json for a single $project.
     *
     * @return bool
     *   True if composer.json needed to be updated.
     *
     */
    protected function disableScaffolding(): bool
    {
        $composer_json = $this->getComposerJson();
        if (false !== $key = array_search($this->package_name, $composer_json['extra']['drupal-scaffold']['allowed-packages'] ?? [])) {
            unset($composer_json['extra']['drupal-scaffold']['allowed-packages'][$key]);
            $this->saveComposerJson($composer_json);
            return true;
        }
        return false;
    }

}
