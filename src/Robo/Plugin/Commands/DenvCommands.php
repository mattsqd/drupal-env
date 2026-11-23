<?php

namespace DrupalEnv\Robo\Plugin\Commands;

use DrupalEnv\Robo\DenvTrait;
use Robo\Result;
use Robo\Tasks;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Shared commands for every developer, whatever the local environment.
 *
 * @class RoboFile
 */
class DenvCommands extends Tasks
{
    use DenvTrait {
        drush as protected traitDrush;
    }

    /**
     * Add the server required to make Xdebug work in PhpStorm.
     *
     * This works with /.run/appserver.run.xml to allow Xdebug to work.
     *
     * @command denv:xdebug-phpstorm-config
     */
    public function xdebugPhpstormDebugConfig(): void
    {
        if (!class_exists('DOMDocument')) {
            throw new \Exception('Your local PHP must have the "dom" extension installed.');
        }
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->preserveWhiteSpace = TRUE;

        if (!@$xml->load(".idea/php.xml")) {
            throw new \Exception('Are you sure your using PhpStorm? There is no /.idea/php.xml file.');
        }
        $components = $xml->getElementsByTagName("component");
        /** @var \DOMElement $row */
        foreach ($components as $row) {
            if ($row->getAttribute('name') === 'PhpProjectServersManager') {
                throw new \Exception('Xdebug is already configured');
            }
        }
        /* Append a component that looks something like:
        <component name="PhpProjectServersManager">
          <servers>
            <server host="doesnotmatter.com" id="fdf5bc85-858f-4732-ba1d-29be7676b0a3" name="appserver" use_path_mappings="true">
              <path_mappings>
                <mapping local-root="$PROJECT_DIR$" remote-root="/app" />
              </path_mappings>
            </server>
          </servers>
        </component>
        */

        $project = $xml->getElementsByTagName('project');

        $mapping = $xml->createElement('mapping', '');
        $mapping->setAttribute('local-root', '$PROJECT_DIR$');
        $mapping->setAttribute('remote-root', '/app');

        $path_mappings = $xml->createElement('path_mappings', '');

        $path_mappings->appendChild($mapping);

        $server = $xml->createElement('server', '');
        $server->setAttribute('host', 'doesnotmatter.com');
        $server->setAttribute('id', $this->genUuidV4());
        $server->setAttribute('name', 'appserver');
        $server->setAttribute('use_path_mappings', 'true');

        $server->appendChild($path_mappings);

        $servers = $xml->createElement('servers', '');

        $servers->appendChild($server);

        $component = $xml->createElement('component');
        $component->setAttribute('name', 'PhpProjectServersManager');

        $component->appendChild($servers);

        $project->item(0)->appendChild($component);
        $xml->save('.idea/php.xml');

    }

    /**
     * Call drush for your local environment.
     *
     * @command denv:drush
     *
     * @param array $args
     *   All arguments and options passed to drush.
     * @param array $opts
     *   Options. "print_output" is passed to the robo taskExec().
     *
     * @option env The local environment to use.
     *
     * @return \Robo\Result
     *
     * @throws \Exception
     */
    public function drush(SymfonyStyle $io, array $args, array $opts = ['env' => null, 'print_output' => true]): Result
    {
        return $this->traitDrush($io, $args, $opts);
    }

    /**
     * Get the path to Drush.
     *
     * @command denv:drush-path
     *
     * @option env The local environment to use.
     *
     * @throws \Exception
     */
    public function drushPath(SymfonyStyle $io, array $opts = ['env' => null]): string
    {
        return $this->getBinaryLocation($io, 'drush', '', false);
    }

    /**
     * Call composer on the local machine.
     *
     * @command denv:composer
     *
     * @param array $args
     *    All arguments and options passed to composer.
     * @param array $opts
     *    Options. "print_output" is passed to the robo taskExec().
     *
     * @option env The local environment to use.
     *
     * @return \Robo\Result
     *
     * @throws \Exception
     */
    public function composer(SymfonyStyle $io, array $args, array $opts = ['env' => null, 'print_output' => true]): Result
    {
        $path_to_composer = $this->getBinaryLocation($io, 'composer', 'docker run --rm -i --tty -v $PWD:/app composer:2');
        $task = $this->taskExec($path_to_composer);
        if (!empty($args)) {
            $task->args($args);
        }
        return $task
            ->printOutput($opts['print_output'] ?? true)
            ->run()
            ->stopOnFail();
    }

    /**
     * Install Drupal.
     *
     * @command denv:site-install
     * @aliases si
     *
     * @option env The local environment to use.
     *
     * @return void
     */
    public function siteInstall(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $this->denvRunScriptInApp($io, 'env DRUPAL_UPDATE_OR_INSTALL=install ./orch/deploy_install.sh; env DRUPAL_SOLR_SITE_HASH=abcdef ./orch/post_deploy.sh; drush uli;');
    }

    /**
     * Update Drupal.
     *
     * @command denv:site-update
     * @aliases su
     *
     * @option env The local environment to use.
     *
     * @return void
     */
    public function siteUpdate(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $this->denvRunScriptInApp($io, 'env DRUPAL_UPDATE_OR_INSTALL=install ./orch/deploy_update.sh; env DRUPAL_SOLR_SITE_HASH=abcdef ./orch/post_deploy.sh; drush uli;');
    }

    /**
     * Remove any extra added to bottom of settings.php.
     *
     * @command denv:remove-settings-php-changes
     *
     * @option env The local environment to use.
     *
     * @return void
     */
    public function removeSettingsPhpChangesCommand(SymfonyStyle $io, array $opts = ['env' => null]): void
    {
        $this->removeSettingsPhpChanges($io);
    }

    /**
     * Allows the user to reset their shortcut paths and see help about them.
     *
     * @command denv:shortcuts-help
     *
     * @return void
     */
    public function shortcutsHelp(SymfonyStyle $io): void
    {
        if ($io->confirm('Would you like to reset your previous selections for PHP & composer paths?', false)) {
            $this->taskFilesystemStack()->remove('.php.env')->run();
            // Reset all common paths.
            // Oddity: You can't set to empty array if an array value is already
            // set. Instead, you have to set it to null.
            $this->saveConfig('flags.common.paths', null, true);
        }
        $this->introduceCommonShortcuts($io, false);
    }

    /**
     * Dump aliases that allow easy access to shortcuts.
     *
     * @description Use shortcuts like `composer` instead of `./composer.sh`.
     *
     * @command denv:shortcuts-aliases
     *
     * @return void
     */
    public function shortcutsAliases(SymfonyStyle $io): void
    {
        $finder = Finder::create()
            ->files()
            ->name('*.sh')
            ->in(getcwd())
            ->depth('== 0');

        if ($finder->hasResults()) {
            $shortcuts = [];
            foreach ($finder as $file) {
                $noExtension = substr($file->getRelativePathname(), 0, -3);
                $shortcuts[] = sprintf(
                    "alias %s='drupalenv_command %s'",
                    $noExtension,
                    $noExtension
                );
            }
            $command = <<<'COMMAND'
# Aliases added for drupal-env to allow easy access to shell scripts.
drupalenv_command() {
  local tool="$1"
  shift
  if [[ -x "./${tool}.sh" ]]; then
    "./${tool}.sh" "$@"
  else
    command "$tool" "$@"
  fi
}
COMMAND;

            $io->warning(
                'These will work for all projects, only add them once.'
            );
            $io->warning(
                'This function is very simple. It looks for the corresponding .sh file in the current directory and calls it if found. If not, it will call the "global" version of the command.'
            );
            $io->block($command);
            $io->block($shortcuts);
            $io->block('# End aliases for drupal-env.');
        } else {
            $io->error(
                'No .sh files found in the root directory, there are no aliases that can be made.'
            );

            return;
        }
        if (!$io->confirm(
            'Would you like help adding these aliases to your shell?'
        )) {
            return;
        }
        $finder = Finder::create()
            ->files()
            ->in(getenv('HOME'))
            ->ignoreDotFiles(false)
            ->contains('alias ')
            ->depth('== 0');

        if ($finder->hasResults()) {
            $aliasesFiles = [];
            foreach ($finder as $file) {
                $aliasesFiles[] = $file->getRealPath();
            }
            $io->info(
                'Read more about aliases here https://en.wikipedia.org/wiki/Alias_(command)'
            );
            $io->info(
                "The following files contain aliases already.\nAdd the above block of code to one of these files.\nAfter you edit the file, you may need to reload your shell or 'source' the file."
            );
            $io->block($aliasesFiles);
        } else {
            $io->warning(
                'Unable to find any files that contain any aliases in your home directory. Please open a new terminal and type echo $SHELL.'
            );
        }
    }

    /**
     * Run an inline script inside the local environment.
     *
     * @throws \Exception
     */
    protected function denvRunScriptInApp(SymfonyStyle $io, string $script): bool
    {
        return $this->_exec($this->getBinaryLocation($io, 'exec', '', false) . " '" . $script . "'")->wasSuccessful();
    }

}
