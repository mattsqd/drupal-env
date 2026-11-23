<?php

namespace DrupalEnv\Upgrade;

/**
 * Checks a 2.x site for blockers to the 3.x upgrade, then cleans up.
 */
final class Upgrade3x
{
    public const OLD_DIR = 'RoboEnv/Robo/Plugin/Commands';
    public const OLD_COPIES = ['CommonAbstract.php', 'CommonCommands.php', 'CommonTrait.php', 'DdevCommands.php', 'LandoCommands.php', '.editorconfig'];
    public const OLD_CLASSES = ['CommonTrait', 'CommonCommands', 'CommonAbstract', 'DdevCommands', 'LandoCommands'];
    public const ENV_CONFIG_FILES = ['.ddev/config.yaml', '.lando.yml', '.lando.dist.yml', '.lando.local.yml'];

    private string $root;

    public function __construct(string $root, private \Closure $isTracked, private string $roboFileTemplate)
    {
        $this->root = rtrim($root, '/');
    }

    /**
     * @return list<array{file: string, line: ?int, reason: string, fix: string}>
     */
    public function findProblems(): array
    {
        $problems = [];
        foreach (self::OLD_COPIES as $name) {
            $rel = self::OLD_DIR . '/' . $name;
            if (is_file("$this->root/$rel") && ($this->isTracked)($rel)) {
                $problems[] = [
                    'file' => $rel,
                    'line' => null,
                    'reason' => 'A 2.x copy is committed to git.',
                    'fix' => 'Move any edits into RoboFile.php hooks, then delete the file.',
                ];
            }
        }
        foreach ($this->siteFiles() as $rel) {
            $lines = file("$this->root/$rel", FILE_IGNORE_NEW_LINES) ?: [];
            $inNamespace = (bool) preg_grep('/^\s*namespace\s+RoboEnv\\\\Robo\\\\Plugin\\\\Commands\s*;/', $lines);
            foreach ($lines as $i => $text) {
                $n = $i + 1;
                foreach (self::OLD_CLASSES as $class) {
                    $fq = (bool) preg_match('/RoboEnv\\\\Robo\\\\Plugin\\\\Commands\\\\' . $class . '\b/', $text);
                    $hit = $fq || ($inNamespace && preg_match('/\b' . $class . '\b/', $text));
                    if ($hit) {
                        $problems[] = [
                            'file' => $rel,
                            'line' => $n,
                            'reason' => "Uses the 2.x class $class.",
                            'fix' => $fq
                                ? 'Switch the use line to \\DrupalEnv\\Robo\\DenvTrait and move customizations into RoboFile.php hooks.'
                                : 'Use \\DrupalEnv\\Robo\\DenvTrait (or drop the reference) and move customizations into RoboFile.php hooks.',
                        ];
                    }
                }
                foreach (CommandRenames::MAP as $old => $new) {
                    if (preg_match('/(?<![\w:-])' . preg_quote($old, '/') . '(?![\w:-])/', $text)) {
                        $problems[] = [
                            'file' => $rel,
                            'line' => $n,
                            'reason' => "Uses the 2.x command name $old.",
                            'fix' => "Replace with $new.",
                        ];
                    }
                }
            }
        }
        return $problems;
    }

    /**
     * @return list<string>
     */
    public function cleanUp(): array
    {
        $actions = [];

        foreach (self::ENV_CONFIG_FILES as $rel) {
            $path = "$this->root/$rel";
            if (!is_file($path)) {
                continue;
            }
            $before = file_get_contents($path);
            $after = $before;
            foreach (CommandRenames::MAP as $old => $new) {
                $after = preg_replace('~(robo\.sh |vendor/bin/robo )' . preg_quote($old, '~') . '(?![\w:-])~', '${1}' . $new, $after);
            }
            if ($after !== $before) {
                $actions[] = false !== @file_put_contents($path, $after)
                    ? "Updated command names in $rel."
                    : "Could not update command names in $rel.";
            }
        }

        $dir = $this->root . '/' . self::OLD_DIR;
        foreach (self::OLD_COPIES as $name) {
            $rel = self::OLD_DIR . '/' . $name;
            if (is_file("$this->root/$rel") && !($this->isTracked)($rel)) {
                $actions[] = @unlink("$this->root/$rel")
                    ? "Deleted untracked 2.x copy $rel."
                    : "Could not delete untracked 2.x copy $rel.";
            }
        }

        $giRel = self::OLD_DIR . '/.gitignore';
        $gi = "$this->root/$giRel";
        if (is_file($gi)) {
            $lines = file($gi, FILE_IGNORE_NEW_LINES) ?: [];
            $names = array_map(fn($n) => ['/' . $n, $n], self::OLD_COPIES);
            $kept = array_values(array_filter($lines, function ($l) use ($names) {
                $t = trim($l);
                foreach ($names as $variants) {
                    if (in_array($t, $variants, true)) {
                        return false;
                    }
                }
                return true;
            }));
            if (count($kept) !== count($lines)) {
                $remaining = array_filter($kept, fn($l) => trim($l) !== '' && !str_starts_with(trim($l), '#'));
                if (!$remaining && !($this->isTracked)($giRel)) {
                    $actions[] = @unlink($gi) ? "Deleted $giRel." : "Could not delete $giRel.";
                } else {
                    $actions[] = false !== @file_put_contents($gi, $kept ? implode("\n", $kept) . "\n" : '')
                        ? "Removed 2.x file names from $giRel."
                        : "Could not update $giRel.";
                }
            }
        }
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            $actions[] = @rmdir($dir)
                ? 'Deleted empty ' . self::OLD_DIR . '.'
                : 'Could not delete empty ' . self::OLD_DIR . '.';
        }

        if ($this->hasTwoXIgnoreLine()) {
            $rootGi = "$this->root/.gitignore";
            $lines = file($rootGi, FILE_IGNORE_NEW_LINES) ?: [];
            $kept = array_values(array_filter($lines, fn($l) => $l !== '/RoboFile.php'));
            $actions[] = false !== @file_put_contents($rootGi, $kept ? implode("\n", $kept) . "\n" : '')
                ? 'Removed /RoboFile.php from .gitignore.'
                : 'Could not remove /RoboFile.php from .gitignore.';
            if (is_file("$this->root/RoboFile.php") && !($this->isTracked)('RoboFile.php')) {
                $actions[] = @copy($this->roboFileTemplate, "$this->root/RoboFile.php")
                    ? 'Replaced the 2.x RoboFile.php placeholder with the 3.x template.'
                    : 'Could not replace RoboFile.php with the 3.x template.';
            }
        }

        return $actions;
    }

    private function hasTwoXIgnoreLine(): bool
    {
        $path = "$this->root/.gitignore";
        if (!is_file($path)) {
            return false;
        }
        return in_array('/RoboFile.php', file($path, FILE_IGNORE_NEW_LINES) ?: [], true);
    }

    /**
     * Site PHP files to scan, relative to the root.
     *
     * @return list<string>
     */
    private function siteFiles(): array
    {
        $files = [];
        $replaceable = $this->hasTwoXIgnoreLine() && !($this->isTracked)('RoboFile.php');
        if (is_file("$this->root/RoboFile.php") && !$replaceable) {
            $files[] = 'RoboFile.php';
        }
        if (is_dir("$this->root/RoboEnv")) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$this->root/RoboEnv", \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->getExtension() !== 'php') {
                    continue;
                }
                $rel = 'RoboEnv/' . substr($f->getPathname(), strlen("$this->root/RoboEnv/"));
                if (dirname($rel) === self::OLD_DIR && in_array($f->getFilename(), self::OLD_COPIES, true)) {
                    continue;
                }
                $files[] = $rel;
            }
            sort($files);
        }
        return $files;
    }
}
