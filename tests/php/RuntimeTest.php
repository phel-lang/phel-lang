<?php

namespace Phel;

use PHPUnit\Framework\TestCase;

class RuntimeMock extends Runtime
{
    public $files = array();
    public $loadedFile = null;

    public function __construct()
    {
        $this->globalEnv = new GlobalEnvironment();
    }

    public function setFiles(array $files)
    {
        $this->files = $files;
    }

    protected function loadFile(string $filename, string $ns): bool
    {
        $this->loadedFile = $filename;
        return true;
    }

    protected function fileExists($filename): bool
    {
        return in_array($filename, $this->files);
    }
}

class RuntimeTest extends TestCase
{

    /**
     * @var RuntimeMock
     */
    protected $runtime;

    public function setUp(): void
    {
        $this->runtime = new RuntimeMock();
        $this->runtime->setFiles([
            '/vendor/foo.bar/src/Core.phel',
            '/vendor/foo.bar/src/Doom.phel',
            '/vendor/foo.bar/tests/Test.phel',
            '/vendor/foo.bardoom/src/Core.phel',
            '/vendor/foo.bar.baz.dib/src/Core.phel',
            '/vendor/foo.bar.baz.dib.zim.gir/src/Core.phel',
        ]);

        $this->runtime->addPath('Foo\\Bar\\', ['/vendor/foo.bar/src', '/vendor/foo.bar/tests']);
        $this->runtime->addPath('Foo\\BarDoom\\', ['/vendor/foo.bardoom/src']);
        $this->runtime->addPath('Foo\\Bar\\Baz\\Dib\\', ['/vendor/foo.bar.baz.dib/src']);
        $this->runtime->addPath('Foo\\Bar\\Baz\\Dib\\Zim\\Gir\\', ['/vendor/foo.bar.baz.dib.zim.gir/src']);
    }

    public function testExistingFile()
    {
        $this->runtime->loadNs('Foo\Bar\Core');
        $this->assertEquals(
            '/vendor/foo.bar/src/Core.phel',
            $this->runtime->loadedFile
        );

        $this->runtime->loadNs('Foo\Bar\Test');
        $this->assertEquals(
            '/vendor/foo.bar/tests/Test.phel',
            $this->runtime->loadedFile
        );
    }

    public function testMissing()
    {
        $this->assertFalse($this->runtime->loadNs('No_Vendor\No_Package\NoClass'));
    }

    public function testDeepFile()
    {
        $this->runtime->loadNs('Foo\Bar\Baz\Dib\Zim\Gir\Core');
        $this->assertEquals(
            '/vendor/foo.bar.baz.dib.zim.gir/src/Core.phel',
            $this->runtime->loadedFile
        );
    }

    public function testConfusion()
    {
        $this->runtime->loadNs('Foo\Bar\Doom');
        $this->assertEquals(
            '/vendor/foo.bar/src/Doom.phel',
            $this->runtime->loadedFile
        );

        $this->runtime->loadNs('Foo\BarDoom\Core');
        $this->assertEquals(
            '/vendor/foo.bardoom/src/Core.phel',
            $this->runtime->loadedFile
        );
    }
}
