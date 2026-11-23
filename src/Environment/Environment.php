<?php

declare(strict_types=1);

namespace DrupalEnv\Environment;

use DrupalEnv\Robo\DenvTrait;
use Robo\Tasks;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base class for a local development environment (DDEV, Lando, ...).
 */
abstract class Environment extends Tasks {

  use DenvTrait;

  /**
   * Machine name, e.g. 'ddev'.
   */
  abstract public static function getName(): string;

  /**
   * Human label, e.g. 'DDEV'.
   */
  abstract public static function getLabel(): string;

  /**
   * The composer command, optionally as run inside the environment.
   */
  abstract public static function composerCommand(bool $inside = true): string;

  /**
   * The drush command, optionally as run inside the environment.
   */
  abstract public static function drushCommand(bool $inside = true): string;

  /**
   * The command prefix used to execute inside the environment.
   */
  abstract public static function execCommand(bool $inside = true): string;

  /**
   * Whether the committed environment config exists.
   */
  abstract public function isSetUp(): bool;

  /**
   * Checks requirements (denv:local-reqs).
   */
  abstract public function reqs(SymfonyStyle $io): bool;

  /**
   * Writes config (denv-admin:local-config).
   */
  abstract public function config(SymfonyStyle $io): void;

  /**
   * Required services (denv-admin:local-services-required).
   */
  abstract public function servicesRequired(SymfonyStyle $io): void;

  /**
   * Optional services (denv-admin:local-services-optional).
   */
  abstract public function servicesOptional(SymfonyStyle $io): void;

  /**
   * Destroys and starts the environment.
   */
  abstract public function rebuild(SymfonyStyle $io): void;

  /**
   * Environment-specific prompts and help text after init.
   */
  public function afterInit(SymfonyStyle $io): void {
  }

}
