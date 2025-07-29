<?php

namespace DrupalEnv\Robo\Plugin\Commands;

use Composer\InstalledVersions;
use Drupal\Component\Utility\Crypt;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Provide commands to handle installation tasks.
 *
 * @class RoboFile
 */
class DrupalEnvCommands extends DrupalEnvCommandsBase
{

    /**
     * {@inheritdoc}
     */
    protected string $package_name = 'mattsqd/drupal-env';

    /**
     * This is the entry point to allow Drupal env and it's plugins to scaffold.
     *
     * Run this to kick off once.
     *
     * @command drupal-env:enable-scaffold
     */
    public function enableScaffoldCommand(SymfonyStyle $io): void
    {
        $this->enableScaffolding($io);
    }

    /**
     * What plugins are allowed to be used.
     *
     * This should be updated if any new plugins are created.
     *
     * @return string[]
     */
    public static function allowedDrupalEnvPluginNames(): array {
        return [
            'lando',
            'ddev',
            // Drupal env itself.
            '',
        ];
    }

    /**
     * Make sure that the orchestration files can be executed.
     *
     * @command drupal-env:allow-orchestration-files-to-execute
     */
    public function allowOrchestrationFilesToExecute(SymfonyStyle $io): void
    {
        if (!is_writable('composer.sh')) {
            return;
        }
        $io->note('Allowing orchestration files to be executed...');
        $this->_exec('chmod -f +x ./orch/*.sh ./composer.sh ./php.sh ./robo.sh ./drush.sh');
    }

    /**
     * Allow drupal env plugins to append to files.
     *
     * Why not just use drupal:scaffold? It doesn't support appending to the
     * same file by different plugins. It also does a poor job of appending
     * again if the content is not exactly the same. This command uses a prefix
     * and suffix wrapper around the content that does not change so that it is
     * easier to update the content without appending multiple times.
     *
     * @command drupal-env:scaffold-append
     */
    public function drupalEnvScaffoldAppend(SymfonyStyle $io): void
    {
        $root_composer = $this->getComposerJson();
        $drupal_scaffold_allowed = $root_composer['extra']['drupal-scaffold']['allowed-packages'] ?? [];
        foreach (self::allowedDrupalEnvPluginNames() as $packageName) {
            $packageName = rtrim('mattsqd/drupal-env-' . $packageName, '-');
            // It may be hardcoded as allowed by drupal env, but to scaffold
            // it must be enabled in the same way that other packages are
            // allowed.
            if (!in_array($packageName, $drupal_scaffold_allowed)) {
                continue;
            }
            if (!InstalledVersions::isInstalled($packageName)) {
                continue;
            }
            $installPath = InstalledVersions::getInstallPath($packageName);
            $composer = json_decode(file_get_contents($installPath . '/composer.json'), true);
            $appending = $composer['extra']['drupal-env']['file-mapping'] ?? [];
            if (empty($appending)) {
                var_dump($packageName);
                var_dump('No files to append to.');
                continue;
            }
            $this->say("$packageName: Looking for files that need to be appended to...");
            foreach ($appending as $target_path => $options) {
                if (false === $options) {
                    continue;
                }
                $comment_start = '####';
                $comment_end = '####';
                if (!is_array($options)) {
                    $source_path = $options;
                } else {
                    $source_path = $options['path'] ?? '';
                    $comment_start = $options['comment_start'] ?? $comment_start;
                    $comment_end = $options['comment_end'] ?? $comment_end;
                }
                $source_path = $installPath . '/' . $source_path;
                if (!is_readable($source_path)) {
                    $io->yell('The source path, ' .$source_path . ', is not readable to append to ' . $target_path . '. Skipping.');
                    continue;
                }
                $this->append($target_path, file_get_contents($source_path), $packageName, $comment_start, $comment_end);
            }
        }
    }
    /**
     * {@inheritDoc}
     */
    public static function preScaffoldCommand(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public static function postScaffoldCommand(): array
    {
        return [
            'drupal-env:allow-orchestration-files-to-execute',
            'drupal-env:scaffold-append',
        ];
    }

    /**
     * Finds all pre or post drupal scaffold commands and runs them.
     *
     * @command drupal-env:drupal-scaffold-cmd
     */
    public function drupalEnvPreDrupalScaffoldCmd(string $pre_or_post): void {
        if (!in_array($pre_or_post, ['pre', 'post'])) {
            throw new \Exception('Type must be either pre or post');
        }
        // This will run the pre drupal scaffold commands from other plugins.
        $this->runScaffoldScriptCommands($pre_or_post);
    }

    /**
     * Run the pre and post scaffold commands.
     *
     * @param string $type Either 'pre' or 'post'.
     *
     * @throws \Exception
     */
    function runScaffoldScriptCommands(string $type = 'pre'): void
    {
        if (!in_array($type, ['pre', 'post'])) {
            throw new \Exception('Type must be either pre or post');
        }

        foreach (self::allowedDrupalEnvPluginNames() as $name) {
            $name = ucfirst($name);
            $call_array = [sprintf('DrupalEnv%s\Robo\Plugin\Commands\DrupalEnv%sCommands', $name, $name), $type . 'ScaffoldCommand'];
            if (method_exists(...$call_array)) {
                $robo_commands = call_user_func($call_array);
                // This is an abstract method, so it has to be implemented, but
                // it can be an empty string to skip.
                foreach ($robo_commands as $robo_command) {
                    $this->_exec('vendor/bin/robo ' . $robo_command);
                }
            }
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function beforeEnableScaffolding(SymfonyStyle $io): void
    {
        // Create the config sync directory if it does not exist.
        if (!is_dir('config/sync')) {
            $io->note('Creating the config sync directory...');
            $this->taskFilesystemStack()->mkdir('config/sync', 0755)->run();
        }

        // Add required composer requirements.
        $io->note('Installing required dependencies...');

        // Add core-dev.
        if (empty($this->getComposerJson()['require-dev']['drupal/core-dev'])) {
            $this->taskComposerRequire($this->getComposerPath())->dependency(
                'drupal/core-dev'
            )->dev()->run();
        }
        // Add Drush.
        if (empty($this->getComposerJson()['require']['drush/drush'])) {
            $this->taskComposerRequire($this->getComposerPath())->dependency(
                'drush/drush'
            )->run();
        }
        // Add cweagans/composer-patches as a dependency. It is needed so that
        // the patch to drupal core scaffolding can be applied right away
        // so that drupal core does not remove .editorconfig scaffolding.
        if (empty($this->getComposerJson()['require']['cweagans/composer-patches'])) {
            $this->taskComposerRequire($this->getComposerPath())->dependency('cweagans/composer-patches')->run();
        }

        $io->success('Your project is now ready to install remote (none yet) and local environments');

        // Create a unique hash_salt for this site before Drupal is installed,
        // that way settings.php does need to be written to which causes
        // $database to be added which is already set.
        if (!file_exists('drupal_hash_salt.txt')) {
            file_put_contents('drupal_hash_salt.txt', Crypt::randomBytesBase64(55));
        }

        // Ensure that settings.php is in place, so it can be appended to by the
        // scaffolding.
        $web_root = $this->getComposerJson()['extra']['drupal-scaffold']['locations']['web-root'] ?? 'web';
        $web_root = rtrim($web_root, '/');
        if (!file_exists("$web_root/sites/default/settings.php") && file_exists("$web_root/sites/default/default.settings.php")) {
            $this->_copy("$web_root/sites/default/default.settings.php", "$web_root/sites/default/settings.php");
        }

        // Define required PSR-4 autoload mappings.
        $values = [
            // Allow robo tasks that are scaffolded in to work.
            'RoboEnv\\' => './RoboEnv/',
            // Allow Drush to be autoloaded from robo commands.
            'Drush\\'   => './vendor/drush/drush/src-symfony-compatibility/v6/',
        ];

        // Get current PSR-4 autoload entries.
        $composer_json['autoload']['psr-4'] = $composer_json['autoload']['psr-4'] ?? [];
        $autoload_psr4 =& $composer_json['autoload']['psr-4'];

        // Find missing mappings.
        $missing = array_diff_assoc($values, $autoload_psr4);

        // Add missing mappings and save if there are changes.
        if (!empty($missing)) {
            $autoload_psr4 = array_merge($autoload_psr4, $missing);
            $this->saveComposerJson($composer_json);
        }

        // The 'gitignore' option must be false so it doesn't start adding files
        // Add robo commands to composer.json pre and post drupal scaffold
        // commands so that this and other plugins can take action.
        $this->addScript('pre-drupal-scaffold-cmd', 'vendor/bin/robo drupal-env:drupal-scaffold-cmd pre');
        $this->addScript('post-drupal-scaffold-cmd', 'vendor/bin/robo drupal-env:drupal-scaffold-cmd post');
    }

    /**
     * Add a $script to a $hook in composer.json.
     *
     * @param string $hook The hook to add the script to (e.g. post-install-cmd).
     * @param string $script The command to run.
     * @param string $partial_match A partial match to check if the script is already there. If not given, defaults to $script.
     *
     * @return bool Returns false if the script was already there unchanged.
     */
    protected function addScript(string $hook, string $script, string $partial_match = ''): bool
    {
        if (!strlen($partial_match)) {
            $partial_match = $script;
        }
        $composer_json = $this->getComposerJson();
        $scripts = $composer_json['scripts'][$hook] ?? [];
        $results = array_filter($scripts, function ($key) use ($scripts, $partial_match) {
            // Only search by this partial text which should never change, that way
            // if the files that get modified get updated, then this command will be
            // updated instead of adding a new.
            return str_contains($scripts[$key], $partial_match);
        }, ARRAY_FILTER_USE_KEY);
        if (!empty($results)) {
            foreach ($results as $key => $result) {
                if ($result !== $script) {
                    $composer_json['scripts'][$hook][$key] = $script;
                    $this->saveComposerJson($composer_json);
                    return true;
                }
            }
            // Script was already there.
            return false;
        } else {
            $composer_json['scripts'][$hook][] = $script;
            $this->saveComposerJson($composer_json);
            return true;
        }
    }

}
