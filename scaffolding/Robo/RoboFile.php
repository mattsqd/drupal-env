<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Custom RoboFile commands and hooks for this project.
 *
 * This is the only file a project needs to customize drupal-env. See the
 * "Customizing with hooks" section of the drupal-env README.
 *
 * @class RoboFile
 */
class RoboFile extends \Robo\Tasks
{
    use \DrupalEnv\Robo\DenvTrait;

    /**
     * Placeholder for your own project's commands.
     *
     * @command drupal-project:custom-command
     *
     * @return void
     *
     * @throws \Exception
     */
    public function customCommand(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $io->comment('This is just a placeholder command, please add your own custom commands here. Please edit : ' . __FILE__);
    }

    // Hook examples. Uncomment and edit to customize drupal-env.
    //
    // Run something before every local init.
    //
    // /**
    //  * @hook pre-command denv:local-init
    //  */
    // public function preLocalInit(\Consolidation\AnnotatedCommand\CommandData $commandData): void
    // {
    //     // Runs before the environment is initialized.
    // }
    //
    // Run something after every local init.
    //
    // /**
    //  * @hook post-command denv:local-init
    //  */
    // public function postLocalInit($result, \Consolidation\AnnotatedCommand\CommandData $commandData): void
    // {
    //     // Runs after the environment is initialized.
    // }
    //
    // Run something after local init, only for one environment.
    //
    // /**
    //  * @hook post-command denv:local-init
    //  */
    // public function postLocalInitDdev($result, \Consolidation\AnnotatedCommand\CommandData $commandData): void
    // {
    //     if ($this->localEnvName() === 'ddev') {
    //         $this->_exec('ddev describe');
    //     }
    // }
    //
    // Replace an environment-backed command for one environment and keep the
    // environment's own behavior for the others. Only the first registered
    // replacement runs.
    //
    // /**
    //  * @hook replace-command denv:local-reqs
    //  */
    // public function replaceLocalReqs(\Symfony\Component\Console\Style\SymfonyStyle $io): \Robo\ResultData
    // {
    //     if ($this->localEnvName() === 'ddev') {
    //         // Do your own DDEV requirements check here.
    //         return new \Robo\ResultData(0);
    //     }
    //     return new \Robo\ResultData($this->localEnv()->reqs($io) ? 0 : 1);
    // }

}
