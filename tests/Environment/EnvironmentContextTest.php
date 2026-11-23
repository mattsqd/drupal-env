<?php

declare(strict_types=1);

namespace DrupalEnv\Tests\Environment;

use DrupalEnv\Environment\EnvironmentContext;
use DrupalEnv\Environment\EnvironmentRegistry;
use DrupalEnv\Tests\Fixtures\FakeEnvironment;
use PHPUnit\Framework\TestCase;

final class EnvironmentContextTest extends TestCase {

  protected function setUp(): void {
    EnvironmentContext::reset();
  }

  public function testResolvesOncePerProcess(): void {
    $calls = 0;
    $f = function () use (&$calls) {
      $calls++;
      return 'ddev';
    };
    self::assertSame('ddev', EnvironmentContext::name($f));
    self::assertSame('ddev', EnvironmentContext::name($f));
    self::assertSame(1, $calls);
  }

  public function testResetClearsMemo(): void {
    $calls = 0;
    $f = function () use (&$calls) {
      $calls++;
      return 'lando';
    };
    EnvironmentContext::name($f);
    EnvironmentContext::reset();
    EnvironmentContext::name($f);
    self::assertSame(2, $calls);
  }

  public function testRegistryMemoizedAndReset(): void {
    $calls = 0;
    $make = function () use (&$calls) {
      $calls++;
      return EnvironmentRegistry::fromPackageDirs([]);
    };
    $a = EnvironmentContext::registry($make);
    self::assertSame($a, EnvironmentContext::registry($make));
    self::assertSame(1, $calls);
    EnvironmentContext::reset();
    EnvironmentContext::registry($make);
    self::assertSame(2, $calls);
  }

  public function testEnvironmentMemoizedAndReset(): void {
    $calls = 0;
    $make = function () use (&$calls) {
      $calls++;
      return new FakeEnvironment();
    };
    $a = EnvironmentContext::environment($make);
    self::assertSame($a, EnvironmentContext::environment($make));
    self::assertSame(1, $calls);
    EnvironmentContext::reset();
    self::assertNotSame($a, EnvironmentContext::environment($make));
    self::assertSame(2, $calls);
  }

}
