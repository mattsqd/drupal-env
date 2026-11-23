<?php

namespace DrupalEnv\Tests\Upgrade;

use DrupalEnv\Upgrade\Upgrade3x;
use PHPUnit\Framework\TestCase;

class Upgrade3xTest extends TestCase
{
    private string $root;
    private array $trackedList = [];
    private const OLD = Upgrade3x::OLD_DIR;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/u3-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->trackedList = [];
        file_put_contents("$this->root/template.php", "<?php // 3.x template\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function put(string $rel, string $content = ''): void
    {
        $p = "$this->root/$rel";
        if (!is_dir(dirname($p))) {
            mkdir(dirname($p), 0777, true);
        }
        file_put_contents($p, $content);
    }

    private function upgrade(): Upgrade3x
    {
        $list = &$this->trackedList;
        return new Upgrade3x($this->root, fn(string $p) => in_array($p, $list, true), "$this->root/template.php");
    }

    public function testCleanSiteHasNoProblemsAndNoActions(): void
    {
        $this->assertSame([], $this->upgrade()->findProblems());
        $this->assertSame([], $this->upgrade()->cleanUp());
    }

    public function testTrackedOldCopyIsAProblem(): void
    {
        $rel = self::OLD . '/DdevCommands.php';
        $this->put($rel, '<?php');
        $this->trackedList = [$rel];
        $p = $this->upgrade()->findProblems();
        $this->assertCount(1, $p);
        $this->assertSame($rel, $p[0]['file']);
    }

    public function testOldTraitUseInRoboFileIsAProblem(): void
    {
        $this->put('RoboFile.php', "<?php\n\nnamespace X;\nuse RoboEnv\\Robo\\Plugin\\Commands\\CommonTrait;\n");
        $this->trackedList = ['RoboFile.php'];
        $p = $this->upgrade()->findProblems();
        $this->assertCount(1, $p);
        $this->assertSame(4, $p[0]['line']);
    }

    public function testBareClassInSiteNamespaceIsAProblem(): void
    {
        $this->put(self::OLD . '/DbCommands.php', "<?php\nnamespace RoboEnv\\Robo\\Plugin\\Commands;\n\nclass DbCommands {\n    use CommonTrait;\n}\n");
        $p = $this->upgrade()->findProblems();
        $this->assertCount(1, $p);
        $this->assertSame(5, $p[0]['line']);
    }

    public function testOldCommandNameIsAProblemWithFix(): void
    {
        $this->put('RoboFile.php', "<?php\n\$this->_exec('./robo.sh common:site-update');\n");
        $this->trackedList = ['RoboFile.php'];
        $p = $this->upgrade()->findProblems();
        $this->assertCount(1, $p);
        $this->assertStringContainsString('denv:site-update', $p[0]['fix']);
    }

    public function testDrupalEnvPlumbingNamesAreNotProblems(): void
    {
        $this->put('RoboFile.php', "<?php\n// vendor/bin/robo drupal-env:drupal-scaffold-cmd pre\n// config:split-group\n");
        $this->trackedList = ['RoboFile.php'];
        $this->assertSame([], $this->upgrade()->findProblems());
    }

    public function testCleanUpDeletesOnlyUntrackedOldCopies(): void
    {
        $this->put(self::OLD . '/CommonTrait.php', '<?php');
        $this->put(self::OLD . '/DbCommands.php', '<?php');
        $this->upgrade()->cleanUp();
        $this->assertFileDoesNotExist("$this->root/" . self::OLD . '/CommonTrait.php');
        $this->assertFileExists("$this->root/" . self::OLD . '/DbCommands.php');
    }

    public function testCleanUpEditsTrackedGitignoreButKeepsIt(): void
    {
        $gi = self::OLD . '/.gitignore';
        $this->put($gi, "# Ignore\n/CommonTrait.php\n");
        $this->trackedList = [$gi];
        $this->upgrade()->cleanUp();
        $this->assertFileExists("$this->root/$gi");
        $this->assertStringNotContainsString('/CommonTrait.php', file_get_contents("$this->root/$gi"));
    }

    public function testCleanUpDeletesUntrackedEmptyGitignoreAndDir(): void
    {
        $this->put(self::OLD . '/.gitignore', "# Ignore\n/CommonTrait.php\n");
        $this->upgrade()->cleanUp();
        $this->assertDirectoryDoesNotExist("$this->root/" . self::OLD);
    }

    public function testCleanUpRewritesDdevHooks(): void
    {
        $this->put('.ddev/config.yaml', "hooks:\n  - exec-host: ./robo.sh common:remove-settings-php-changes\n");
        $this->upgrade()->cleanUp();
        $this->assertStringContainsString('robo.sh denv:remove-settings-php-changes', file_get_contents("$this->root/.ddev/config.yaml"));
    }

    public function testRoboFileReplacedWhenTwoXIgnored(): void
    {
        $this->put('.gitignore', "/vendor\n/RoboFile.php\n");
        $this->put('RoboFile.php', '<?php // 2.x');
        $this->upgrade()->cleanUp();
        $this->assertSame("<?php // 3.x template\n", file_get_contents("$this->root/RoboFile.php"));
        $this->assertStringNotContainsString('/RoboFile.php', file_get_contents("$this->root/.gitignore"));
    }

    public function testUntrackedRoboFileKeptWithoutTwoXIgnoreLine(): void
    {
        $this->put('.gitignore', "/vendor\n");
        $this->put('RoboFile.php', '<?php // hooks');
        $this->upgrade()->cleanUp();
        $this->assertSame('<?php // hooks', file_get_contents("$this->root/RoboFile.php"));
    }

    public function testTrackedRoboFileNeverReplaced(): void
    {
        $this->put('.gitignore', "/RoboFile.php\n");
        $this->put('RoboFile.php', '<?php // mine');
        $this->trackedList = ['RoboFile.php'];
        $this->upgrade()->cleanUp();
        $this->assertSame('<?php // mine', file_get_contents("$this->root/RoboFile.php"));
        $this->assertStringNotContainsString('/RoboFile.php', file_get_contents("$this->root/.gitignore"));
    }

    public function testReplaceablePlaceholderIsNotScanned(): void
    {
        $this->put('.gitignore', "/RoboFile.php\n");
        $this->put('RoboFile.php', "<?php\n// common:site-update\n");
        $this->assertSame([], $this->upgrade()->findProblems());
    }

    public function testCleanUpNeverDeletesTrackedOldCopy(): void
    {
        $rel = self::OLD . '/CommonTrait.php';
        $this->put($rel, '<?php');
        $this->trackedList = [$rel];
        $this->upgrade()->cleanUp();
        $this->assertFileExists("$this->root/$rel");
    }

    public function testSimilarlyNamedClassIsNotAProblem(): void
    {
        $this->put('RoboFile.php', "<?php\nuse RoboEnv\\Robo\\Plugin\\Commands\\CommonTraitFoo;\n");
        $this->trackedList = ['RoboFile.php'];
        $this->assertSame([], $this->upgrade()->findProblems());
    }
}
