<?php

declare(strict_types=1);

namespace DrupalEnv\Environment;

/**
 * Picks the local environment a command acts on.
 */
final class EnvironmentResolver {

  /**
   * Resolves the environment name.
   *
   * Order: --env option, active environment, the only installed one, then
   * the preferred one (prompting when interactive and several are installed).
   *
   * @param string|null $option
   *   Value of --env, if given.
   * @param string|null $active
   *   The active environment name.
   * @param string[] $installed
   *   Installed environment names.
   * @param string|null $preferred
   *   The preferred environment name.
   * @param bool $interactive
   *   Whether prompting is possible.
   * @param callable(string[] $choices, ?string $default): string $ask
   *   Prompts the user to choose.
   *
   * @throws \RuntimeException
   */
  public static function resolve(?string $option, ?string $active, array $installed, ?string $preferred, bool $interactive, callable $ask): string {
    $list = implode(', ', $installed);
    if ($option !== null && $option !== '') {
      if (!in_array($option, $installed, true)) {
        throw new \RuntimeException("The local environment '$option' is not installed. Installed: $list. Run ./robo.sh denv-admin:local to install it.");
      }
      return $option;
    }
    if ($active !== null && $active !== '') {
      if (!in_array($active, $installed, true)) {
        throw new \RuntimeException("Your active local environment '$active' is no longer installed. Run ./robo.sh denv-admin:local, or pass --env=<name>.");
      }
      return $active;
    }
    if ($installed === []) {
      throw new \RuntimeException('No local environment is installed. Run ./robo.sh denv-admin:local.');
    }
    if (count($installed) === 1) {
      return reset($installed);
    }
    $preferred = in_array($preferred, $installed, true) ? $preferred : null;
    if (!$interactive) {
      if ($preferred === null) {
        throw new \RuntimeException("Several local environments are installed ($list) and none is preferred. Pass --env=<name>.");
      }
      return $preferred;
    }
    return $ask(array_values($installed), $preferred);
  }

}
