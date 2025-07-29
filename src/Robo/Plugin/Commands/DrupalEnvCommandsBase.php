<?php

namespace DrupalEnv\Robo\Plugin\Commands;

use Robo\Tasks;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        if (`which composer`) {
            return 'composer';
        } elseif (`which docker`) {
            return 'docker run --rm -i --tty -v $PWD:/app composer:2';
        }
        throw new \Exception('Either composer or docker must be installed to continue');
    }

    /**
     * Do tasks needed before scaffolding is enabled and done.
     *
     * @return void
     */
    abstract protected function beforeEnableScaffolding(SymfonyStyle $io): void;

    /**
     * Run this robo command before scaffolding.
     *
     * Return empty array to skip.
     *
     * @return array
     *   An array of robo commands to run.
     */
    abstract public static function preScaffoldCommand(): array;

    /**
     * Run this robo command before scaffolding.
     *
     * Return empty array to skip.
     *
     * @return array
     *    An array of robo commands to run
     */
    abstract public static function postScaffoldCommand(): array;

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
     * @return @void
     */
    protected function enableScaffolding(SymfonyStyle $io): void
    {
        $composer_json = $this->getComposerJson();
        if (!in_array($this->getPackageName(), $composer_json['extra']['drupal-scaffold']['allowed-packages'] ?? [])) {
            if (method_exists($this, 'beforeEnableScaffolding')) {
                $this->beforeEnableScaffolding($io);
                // This will modfy composer.json, reload its value.
                $composer_json = $this->getComposerJson();
            }
            $io->note('Enabling scaffolding for ' . $this->getPackageName());
            $composer_json['extra']['drupal-scaffold']['allowed-packages'][] = $this->getPackageName();
            $this->saveComposerJson($composer_json);
            // Composer.json has been updated manually, update composer.lock.
            $this->_exec($this->getComposerPath() . ' update --lock');
            //$this->_exec($composer_path . ' config extra.drupal-scaffold.allowed-packages --json --merge \'["mattsqd/drupal-env"]\'');
            // Now that scaffolding is enabled, run composer install so that
            // scaffolding is run.
            $this->taskComposerInstall($this->getComposerPath())->run();
        } else {
            $io->note('Scaffolding already enabled for ' . $this->getPackageName());
        }
    }

    /**
     * Append $content to $target_file surrounded by comments.
     *
     * If the comments already exist and the $content changes, just the body
     * of the file will be updated.
     *
     * @param string $target_file The file to append to.
     * @param string $content The content to append.
     * @param string $packageName The name of the package that is appending.
     * @param string $comment_start What to put at the start the comments at the start and end with. Usually '#'.
     * @param string $comment_end What to put at the end of the comments at the start and end with. Usually empty, although an HTML comment might be !-->.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function append($target_file, string $content, string $packageName, string $comment_start, string $comment_end): void
    {
        // Ensure web root ends in "/".
        $web_root = $this->getComposerJson()['extra']['drupal-scaffold']['locations']['web-root'] ?? 'web';

        $target_file = str_replace([
                '[web-root]',
                '[project-root]/'
            ], [
                trim($web_root, '/'),
                '',
            ], $target_file
        );

        if (!file_exists($target_file)) {
            $this->taskFilesystemStack()->touch($target_file)->run();
        }
        if (!is_writable($target_file)) {
            throw new \Exception('File is not writable: ' . $target_file);
        }

        $original = file_get_contents($target_file);

        if (strlen($comment_end)) {
            $comment_end = " $comment_end";
        }
        $prefix = "\n$comment_start Appended to by $packageName.$comment_end\n\n";
        $suffix = "\n$comment_start End of appended content by $packageName.$comment_end\n";

        $new_contents = $prefix . $content . $suffix;

        // Prefix does not exist, so append.
        if (strpos($original, $prefix) === false) {
            $this->taskWriteToFile($target_file)
                ->text($new_contents)
                ->append(true)
                ->run();
        } else {
            // Prefix exists, so replace.
            $pattern = sprintf(
                '/%1$s.*?%2$s/s',
                preg_quote($prefix, '/'),
                preg_quote($suffix, '/')
            );
            // Simulate the replacement.
            $updated = preg_replace($pattern, $new_contents, $original);
            // Only write if the replacement is different.
            if ($original !== $updated) {
                $this->taskWriteToFile($target_file)
                    ->text($updated)
                    ->run();
            }
        }

    }

}
