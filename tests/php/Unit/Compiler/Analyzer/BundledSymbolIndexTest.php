<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\BundledSymbolIndex;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class BundledSymbolIndexTest extends TestCase
{
    private string $stdlibDir;

    protected function setUp(): void
    {
        $this->stdlibDir = sys_get_temp_dir() . '/' . uniqid('phel-bundled-index-', true);
        mkdir($this->stdlibDir . '/core', recursive: true);

        file_put_contents($this->stdlibDir . '/text.phel', <<<'PHEL'
            (ns phel.text
              (:require phel.core))

            (defn shout [s] s)
            (defn- helper [s] s)
            (def ^:private secret 1)
            (defmacro loudly [& body] body)
            (def tone :high)
            PHEL);
        file_put_contents($this->stdlibDir . '/core.phel', "(ns phel.core)\n(defn shout [s] s)\n");
        file_put_contents($this->stdlibDir . '/core/more.phel', "(in-ns phel.core)\n(defn whisper [s] s)\n");
    }

    protected function tearDown(): void
    {
        unlink($this->stdlibDir . '/text.phel');
        unlink($this->stdlibDir . '/core.phel');
        unlink($this->stdlibDir . '/core/more.phel');
        rmdir($this->stdlibDir . '/core');
        rmdir($this->stdlibDir);
    }

    public function test_lists_the_public_names_of_each_namespace(): void
    {
        $index = new BundledSymbolIndex($this->stdlibDir);

        self::assertSame(['phel.text'], $index->namespaces());
        self::assertSame(['shout', 'loudly', 'tone'], $index->namesIn('phel.text'));
    }

    public function test_finds_the_namespaces_defining_a_name(): void
    {
        $index = new BundledSymbolIndex($this->stdlibDir);

        self::assertSame(['phel.text'], $index->namespacesDefining('shout'));
        self::assertSame([], $index->namespacesDefining('helper'));
        self::assertSame([], $index->namespacesDefining('whisper'));
    }
}
