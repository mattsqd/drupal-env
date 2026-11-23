<?php

namespace DrupalEnv\Tests\Upgrade;

use DrupalEnv\Upgrade\GitTracked;
use PHPUnit\Framework\TestCase;

class GitTrackedTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gt-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testNonGitDirTreatsEverythingAsTracked(): void
    {
        $this->assertTrue((GitTracked::forRoot($this->dir))('anything'));
    }

    public function testGitDirReportsUntracked(): void
    {
        $d = escapeshellarg($this->dir);
        exec("git init -q $d");
        file_put_contents("$this->dir/a.txt", 'x');
        $f = GitTracked::forRoot($this->dir);
        $this->assertFalse($f('a.txt'));
        exec("git -C $d add a.txt");
        $this->assertTrue($f('a.txt'));
    }

    public function testGitFatalIsTreatedAsTracked(): void
    {
        $d = escapeshellarg($this->dir);
        exec("git init -q $d");
        file_put_contents("$this->dir/a.txt", 'x');
        exec("git -C $d add a.txt");
        $f = GitTracked::forRoot($this->dir);
        file_put_contents("$this->dir/.git/index", 'corrupt');
        $this->assertTrue($f('a.txt'));
        $this->assertTrue($f('other.txt'));
    }
}
