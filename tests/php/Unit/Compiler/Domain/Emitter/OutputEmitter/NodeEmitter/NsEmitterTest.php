<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\NsEmitter;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class NsEmitterTest extends TestCase
{
    private NsEmitter $nsEmitter;

    protected function setUp(): void
    {
        $outputEmitter = new CompilerFactory()
            ->createOutputEmitter();

        $this->nsEmitter = new NsEmitter($outputEmitter);
    }

    public function test_ns_preserves_hyphens_in_ns_var(): void
    {
        $node = new NsNode('my-great\\ns', [], []);

        ob_start();
        $this->nsEmitter->emit($node);
        $output = (string) ob_get_clean();

        self::assertStringContainsString(
            '"my-great.ns"',
            $output,
            'The *ns* definition should contain the original hyphenated namespace in display form',
        );

        self::assertStringNotContainsString(
            '"my_great.ns"',
            $output,
            'The *ns* definition should not contain the munged namespace',
        );
    }

    public function test_ns_without_hyphens_is_unchanged(): void
    {
        $node = new NsNode('app\\module', [], []);

        ob_start();
        $this->nsEmitter->emit($node);
        $output = (string) ob_get_clean();

        self::assertStringContainsString('"app.module"', $output);
    }

    /**
     * Only `phel repl` sets `*repl-mode*`, so gating the source-directory lookup
     * on it left `phel eval` and the nREPL server searching an empty path, and
     * every `(:require my.ns)` of a project namespace silently loaded nothing
     * (#2886).
     */
    public function test_ns_with_requires_resolves_source_directories_without_a_repl_gate(): void
    {
        $node = new NsNode('my\\app', [Symbol::create('phel\\string')], []);

        ob_start();
        $this->nsEmitter->emit($node);
        $output = (string) ob_get_clean();

        self::assertStringNotContainsString(
            '*repl-mode*',
            $output,
            'The source-directory lookup must not be gated on REPL mode',
        );
        self::assertStringContainsString(
            'getAllPhelDirectories',
            $output,
            'Fallback should use CommandFacade to resolve directories',
        );
    }
}
