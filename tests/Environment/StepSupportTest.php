<?php

declare(strict_types=1);

namespace DrupalEnv\Tests\Environment;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use DrupalEnv\Environment\Feature\SupportsUrls;
use DrupalEnv\Environment\Feature\SupportsXdebugDrush;
use DrupalEnv\Environment\StepSupport;
use DrupalEnv\Tests\Fixtures\FakeEnvironment;
use PHPUnit\Framework\TestCase;

final class StepSupportTest extends TestCase {

  public function testImplementsFeature(): void {
    self::assertTrue(StepSupport::isSupported(FakeEnvironment::class, SupportsUrls::class, new HookManager(), 'denv:local-urls'));
  }

  public function testMissingFeature(): void {
    self::assertFalse(StepSupport::isSupported(FakeEnvironment::class, SupportsXdebugDrush::class, new HookManager(), 'denv:xdebug-toggle-drush'));
  }

  public function testReplaceHookCountsAsSupport(): void {
    $hooks = new HookManager();
    $hooks->add(fn() => null, HookManager::REPLACE_COMMAND_HOOK, 'denv:xdebug-toggle-drush');
    self::assertTrue(StepSupport::isSupported(FakeEnvironment::class, SupportsXdebugDrush::class, $hooks, 'denv:xdebug-toggle-drush'));
  }

  public function testReplaceHookOnOtherCommandIgnored(): void {
    $hooks = new HookManager();
    $hooks->add(fn() => null, HookManager::REPLACE_COMMAND_HOOK, 'denv:local-urls');
    self::assertFalse(StepSupport::isSupported(FakeEnvironment::class, SupportsXdebugDrush::class, $hooks, 'denv:xdebug-toggle-drush'));
  }

}
