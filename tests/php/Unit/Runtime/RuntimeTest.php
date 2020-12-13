<?php

declare(strict_types=1);

namespace PhelTest\Unit\Runtime;

use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase
{
    private RuntimeMock $runtime;

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

    public function testExistingFile(): void
    {
        $this->runtime->loadNs('Foo\Bar\Core');
        self::assertEquals(
            '/vendor/foo.bar/src/Core.phel',
            $this->runtime->loadedFile
        );

        $this->runtime->loadNs('Foo\Bar\Test');
        self::assertEquals(
            '/vendor/foo.bar/tests/Test.phel',
            $this->runtime->loadedFile
        );
    }

    public function testMissing(): void
    {
        self::assertFalse($this->runtime->loadNs('No_Vendor\No_Package\NoClass'));
    }

    public function testDeepFile(): void
    {
        $this->runtime->loadNs('Foo\Bar\Baz\Dib\Zim\Gir\Core');
        self::assertEquals(
            '/vendor/foo.bar.baz.dib.zim.gir/src/Core.phel',
            $this->runtime->loadedFile
        );
    }

    public function testConfusion(): void
    {
        $this->runtime->loadNs('Foo\Bar\Doom');
        self::assertEquals(
            '/vendor/foo.bar/src/Doom.phel',
            $this->runtime->loadedFile
        );

        $this->runtime->loadNs('Foo\BarDoom\Core');
        self::assertEquals(
            '/vendor/foo.bardoom/src/Core.phel',
            $this->runtime->loadedFile
        );
    }
}
