<?php

declare(strict_types=1);

namespace DrupalEnv\Environment;

/**
 * Process-wide memo of the resolved environment name.
 *
 * Lets a hook and its command resolve the environment (and prompt) once.
 */
final class EnvironmentContext {

  private static ?string $name = NULL;

  private static ?EnvironmentRegistry $registry = NULL;

  private static ?Environment $environment = NULL;

  /**
   * Returns the memoized name, calling $resolve once per process.
   *
   * @param callable(): string $resolve
   */
  public static function name(callable $resolve): string {
    return self::$name ??= $resolve();
  }

  /**
   * Returns the memoized registry, calling $make once per process.
   *
   * @param callable(): EnvironmentRegistry $make
   */
  public static function registry(callable $make): EnvironmentRegistry {
    return self::$registry ??= $make();
  }

  /**
   * Returns the memoized environment instance, calling $make once.
   *
   * @param callable(): Environment $make
   */
  public static function environment(callable $make): Environment {
    return self::$environment ??= $make();
  }

  /**
   * Clears all memoized state.
   */
  public static function reset(): void {
    self::$name = NULL;
    self::$registry = NULL;
    self::$environment = NULL;
  }

}
