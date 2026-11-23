<?php

declare(strict_types=1);

namespace DrupalEnv\Environment;

use Consolidation\AnnotatedCommand\Hooks\HookManager;

/**
 * Decides whether an environment supports an optional step.
 */
final class StepSupport {

  /**
   * True if the class implements the feature or a replace hook exists.
   *
   * @param class-string<\DrupalEnv\Environment\Environment> $envClass
   */
  public static function isSupported(string $envClass, string $feature, HookManager $hooks, string $command): bool {
    if (is_subclass_of($envClass, $feature)) {
      return true;
    }
    return $hooks->get([$command], [HookManager::REPLACE_COMMAND_HOOK]) !== [];
  }

}
