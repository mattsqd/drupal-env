<?php

namespace DrupalEnv\Robo\Plugin\Commands;

use Drupal\Component\Utility\Crypt;

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
     * {@inheritdoc}
     */
    protected function preScaffoldChanges(): void
    {
        // Create a unique hash_salt for this site before Drupal is installed,
        // that way settings.php does need to be written to which causes
        // $database to be added which is already set.
        if (!file_exists('drupal_hash_salt.txt')) {
            file_put_contents('drupal_hash_salt.txt', Crypt::randomBytesBase64(55));
        }

        // Add cweagans/composer-patches as a dependency. It is needed so that
        // the patch to drupal core scaffolding can be applied right away
        // so that drupal core does not remove .editorconfig scaffolding.
        $composer_json = $this->getComposerJson();
        if (empty($composer_json['require']['cweagans/composer-patches'])) {
            $this->taskComposerRequire($this->getComposerPath())->dependency('cweagans/composer-patches')->run();
        }

        // Ensure that settings.php is in place, so it can be appended to by the
        // scaffolding.
        $composer_json = $this->getComposerJson();
        $web_root = $composer_json['extra']['drupal-scaffold']['locations']['web-root'] ?? 'web';
        $web_root = rtrim($web_root, '/');
        if (!file_exists("$web_root/sites/default/settings.php") && file_exists("$web_root/sites/default/default.settings.php")) {
            $this->_copy("$web_root/sites/default/default.settings.php", "$web_root/sites/default/settings.php");
        }

        // Add autoloading so that the robo tasks that are scaffolded in will work.
        if (!in_array('./RoboEnv/', $composer_json['autoload']['psr-4'] ?? [])) {
            $composer_json['autoload']['psr-4']['RoboEnv\\'] = './RoboEnv/';
            $this->saveComposerJson($composer_json);
        }

        // Ensure .gitignore exists, so it can be appended to.
        if (!file_exists('.gitignore')) {
            $this->taskFilesystemStack()->touch('.gitignore')->run();
        }

        // The 'gitignore' option must be false so it doesn't start adding files
        // to git ignore that are scaffolded.
        $composer_json = $this->getComposerJson();
        if ($composer_json['extra']['drupal-scaffold']['gitignore'] ?? null !== false) {
            $composer_json['extra']['drupal-scaffold']['gitignore'] = false;
            $this->saveComposerJson($composer_json);
        }

        // Ensure orchestration and shortcuts can be executed.
        $composer_json = $this->getComposerJson();
        $post_drupal_scaffold_cmds = $composer_json['scripts']['post-drupal-scaffold-cmd'] ?? [];
        $results = array_filter($post_drupal_scaffold_cmds, function ($key) use ($post_drupal_scaffold_cmds) {
            // Only search by this partial text which should never change, that way
            // if the files that get modified get updated, then this command will be
            // updated instead of adding a new.
            return str_contains($post_drupal_scaffold_cmds[$key], 'Allowing orchestration files to be executed');
        }, ARRAY_FILTER_USE_KEY);
        $post_drupal_scaffold_cmd = "echo 'Allowing orchestration files to be executed...' & chmod -f +x ./orch/*.sh ./composer.sh ./php.sh ./robo.sh ./drush.sh";
        if (!empty($results)) {
            foreach ($results as $key => $result) {
                if ($result !== $post_drupal_scaffold_cmd) {
                    $composer_json['scripts']['post-drupal-scaffold-cmd'][$key] = $post_drupal_scaffold_cmd;
                    $this->saveComposerJson($composer_json);
                }
            }
        } else {
            $composer_json['scripts']['post-drupal-scaffold-cmd'][] = $post_drupal_scaffold_cmd;
            $this->saveComposerJson($composer_json);
        }

        // Ensure that the patches-file is set.
        $composer_json = $this->getComposerJson();
        if (($composer_json['extra']['patches-file'] ?? '') !== 'composer.patches.json') {
            $this->taskComposerConfig($this->getComposerPath())->set('extra.patches-file', 'composer.patches.json')->run();
        }
    }

}
