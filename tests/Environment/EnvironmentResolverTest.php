<?php

declare(strict_types=1);

namespace DrupalEnv\Tests\Environment;

use DrupalEnv\Environment\EnvironmentResolver;
use PHPUnit\Framework\TestCase;

final class EnvironmentResolverTest extends TestCase {

  private function noPrompt(): callable {
    return fn() => throw new \LogicException('must not prompt');
  }

  public function testOptionWins(): void {
    self::assertSame('lando', EnvironmentResolver::resolve('lando', 'ddev', ['ddev', 'lando'], 'ddev', true, $this->noPrompt()));
  }

  public function testOptionNotInstalled(): void {
    $this->expectExceptionMessage("The local environment 'docksal' is not installed.");
    EnvironmentResolver::resolve('docksal', null, ['ddev'], null, true, $this->noPrompt());
  }

  public function testActiveWins(): void {
    self::assertSame('ddev', EnvironmentResolver::resolve(null, 'ddev', ['ddev', 'lando'], 'lando', true, $this->noPrompt()));
  }

  public function testActiveNoLongerInstalled(): void {
    $this->expectExceptionMessage('denv-admin:local');
    EnvironmentResolver::resolve(null, 'lando', ['ddev'], null, true, $this->noPrompt());
  }

  public function testOnlyOneInstalledNoPrompt(): void {
    self::assertSame('ddev', EnvironmentResolver::resolve(null, null, ['ddev'], null, true, $this->noPrompt()));
  }

  public function testNoneInstalled(): void {
    $this->expectExceptionMessage('No local environment is installed');
    EnvironmentResolver::resolve(null, null, [], null, true, $this->noPrompt());
  }

  public function testSeveralPromptsWithPreferredDefault(): void {
    $seen = null;
    $r = EnvironmentResolver::resolve(null, null, ['ddev', 'lando'], 'lando', true, function ($c, $d) use (&$seen) {
      $seen = [$c, $d];
      return 'lando';
    });
    self::assertSame('lando', $r);
    self::assertSame([['ddev', 'lando'], 'lando'], $seen);
  }

  public function testSeveralPromptsWithNoDefault(): void {
    $seen = 'unset';
    EnvironmentResolver::resolve(null, null, ['ddev', 'lando'], null, true, function ($c, $d) use (&$seen) {
      $seen = $d;
      return 'ddev';
    });
    self::assertNull($seen);
  }

  public function testPreferredNotInstalledIsIgnored(): void {
    $seen = 'unset';
    EnvironmentResolver::resolve(null, null, ['ddev', 'lando'], 'docksal', true, function ($c, $d) use (&$seen) {
      $seen = $d;
      return 'ddev';
    });
    self::assertNull($seen);
  }

  public function testNonInteractiveUsesPreferred(): void {
    self::assertSame('ddev', EnvironmentResolver::resolve(null, null, ['ddev', 'lando'], 'ddev', false, $this->noPrompt()));
  }

  public function testNonInteractiveNoPreferredFails(): void {
    $this->expectExceptionMessage('Pass --env=<name>');
    EnvironmentResolver::resolve(null, null, ['ddev', 'lando'], null, false, $this->noPrompt());
  }

}
