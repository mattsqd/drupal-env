<?php

declare(strict_types=1);

namespace DrupalEnv\Environment;

use Composer\InstalledVersions;

/**
 * Maps environment names to the classes provided by installed packages.
 */
final class EnvironmentRegistry {

  /**
   * @var array<string, class-string<\DrupalEnv\Environment\Environment>>
   */
  private array $map;

  /**
   * @param array<string, class-string<\DrupalEnv\Environment\Environment>> $map
   *   Environment name to class.
   *
   * @throws \RuntimeException
   */
  public function __construct(array $map) {
    foreach ($map as $name => $class) {
      self::validate((string) $name, (string) $class, 'constructor');
    }
    ksort($map);
    $this->map = $map;
  }

  /**
   * Builds the registry from all installed packages of type robo-tasks.
   */
  public static function fromInstalledPackages(): self {
    $dirs = [];
    foreach (InstalledVersions::getInstalledPackagesByType('robo-tasks') as $package) {
      $path = InstalledVersions::getInstallPath($package);
      if ($path !== null) {
        $dirs[] = $path;
      }
    }
    return self::fromPackageDirs($dirs);
  }

  /**
   * Builds the registry from on-disk composer.json files.
   *
   * @param string[] $packageDirs
   *   Package directories.
   *
   * @throws \RuntimeException
   */
  public static function fromPackageDirs(array $packageDirs): self {
    $map = [];
    foreach ($packageDirs as $dir) {
      $file = rtrim($dir, '/') . '/composer.json';
      if (!is_file($file)) {
        continue;
      }
      $composer = json_decode((string) file_get_contents($file), true);
      $environments = is_array($composer) ? ($composer['extra']['drupal-env']['environments'] ?? []) : [];
      if (!is_array($environments)) {
        continue;
      }
      foreach ($environments as $name => $class) {
        self::validate((string) $name, (string) $class, $dir);
        $map[(string) $name] = (string) $class;
      }
    }
    return new self($map);
  }

  /**
   * Sorted environment names.
   *
   * @return string[]
   *   Names.
   */
  public function names(): array {
    return array_map('strval', array_keys($this->map));
  }

  /**
   * Whether an environment is known.
   */
  public function has(string $name): bool {
    return isset($this->map[$name]);
  }

  /**
   * The class for an environment.
   *
   * @return class-string<\DrupalEnv\Environment\Environment>
   *   Class name.
   *
   * @throws \RuntimeException
   */
  public function classFor(string $name): string {
    if (!$this->has($name)) {
      throw new \RuntimeException("No installed package provides the local environment '$name'. Installed: " . implode(', ', $this->names()) . '.');
    }
    return $this->map[$name];
  }

  /**
   * Validates a name/class pair.
   *
   * @throws \RuntimeException
   */
  private static function validate(string $name, string $class, string $source): void {
    if (!class_exists($class)) {
      throw new \RuntimeException("Environment class $class from $source does not exist.");
    }
    if (!is_subclass_of($class, Environment::class)) {
      throw new \RuntimeException("Environment class $class from $source does not extend " . Environment::class . '.');
    }
    if ($class::getName() !== $name) {
      throw new \RuntimeException("Environment class $class from $source is registered as '$name' but its getName() returns '" . $class::getName() . "'.");
    }
  }

}
