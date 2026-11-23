<?php

declare(strict_types=1);

namespace DrupalEnv\Tests\Environment;

use DrupalEnv\Environment\EnvironmentRegistry;
use DrupalEnv\Tests\Fixtures\FakeEnvironment;
use DrupalEnv\Tests\Fixtures\PlainClass;
use PHPUnit\Framework\TestCase;

final class EnvironmentRegistryTest extends TestCase {

  public function testReadsEnvironmentsFromPackageDirs(): void {
    $r = EnvironmentRegistry::fromPackageDirs([__DIR__ . '/../fixture-packages/good', __DIR__ . '/../fixture-packages/notenv']);
    self::assertSame(['fake'], $r->names());
    self::assertTrue($r->has('fake'));
    self::assertSame(FakeEnvironment::class, $r->classFor('fake'));
  }

  public function testUnknownName(): void {
    $this->expectExceptionMessage("No installed package provides the local environment 'lando'");
    (new EnvironmentRegistry([]))->classFor('lando');
  }

  public function testNameMismatchRejected(): void {
    $this->expectException(\RuntimeException::class);
    EnvironmentRegistry::fromPackageDirs([__DIR__ . '/../fixture-packages/mismatch']);
  }

  public function testNonEnvironmentClassRejected(): void {
    $this->expectException(\RuntimeException::class);
    new EnvironmentRegistry(['plain' => PlainClass::class]);
  }

}
