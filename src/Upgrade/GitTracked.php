<?php

namespace DrupalEnv\Upgrade;

/**
 * Answers whether git tracks a file.
 */
final class GitTracked
{
    /**
     * @return \Closure(string $relativePath): bool
     *   Always true when $root is not a git work tree or git is unavailable.
     */
    public static function forRoot(string $root): \Closure
    {
        $dir = escapeshellarg($root);
        $out = [];
        $code = 1;
        @exec("git -C $dir rev-parse --is-inside-work-tree 2>/dev/null", $out, $code);
        $inTree = $code === 0 && trim($out[0] ?? '') === 'true';

        return static function (string $relativePath) use ($dir, $inTree): bool {
            if (!$inTree) {
                return true;
            }
            $o = [];
            $code = 128;
            @exec("git -C $dir ls-files --error-unmatch -- " . escapeshellarg($relativePath) . ' >/dev/null 2>&1', $o, $code);
            // Only an explicit "not matched" (exit 1) means untracked. Any
            // other failure is treated as tracked so nothing is deleted.
            return $code !== 1;
        };
    }
}
