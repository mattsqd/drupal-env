<?php

declare(strict_types=1);

namespace DrupalEnv\Tests\Fixtures;

use DrupalEnv\Environment\Environment;
use DrupalEnv\Environment\Feature\SupportsUrls;
use Symfony\Component\Console\Style\SymfonyStyle;

class FakeEnvironment extends Environment implements SupportsUrls {

  public static function getName(): string {
    return 'fake';
  }

  public static function getLabel(): string {
    return 'Fake';
  }

  public static function composerCommand(bool $inside = true): string {
    return 'composer';
  }

  public static function drushCommand(bool $inside = true): string {
    return 'drush';
  }

  public static function execCommand(bool $inside = true): string {
    return 'exec';
  }

  public function isSetUp(): bool {
    return true;
  }

  public function reqs(SymfonyStyle $io): bool {
    return true;
  }

  public function config(SymfonyStyle $io): void {
  }

  public function servicesRequired(SymfonyStyle $io): void {
  }

  public function servicesOptional(SymfonyStyle $io): void {
  }

  public function rebuild(SymfonyStyle $io): void {
  }

  public function urls(SymfonyStyle $io): bool {
    return true;
  }

}
