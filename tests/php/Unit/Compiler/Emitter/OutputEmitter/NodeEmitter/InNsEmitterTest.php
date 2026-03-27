<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\InNsEmitter;
use PHPUnit\Framework\TestCase;

final class InNsEmitterTest extends TestCase
{
    private InNsEmitter $inNsEmitter;

    protected function setUp(): void
    {
        $outputEmitter = (new CompilerFactory())
            ->createOutputEmitter();

        $this->inNsEmitter = new InNsEmitter($outputEmitter);
    }

    public function test_in_ns_preserves_hyphens_in_ns_var(): void
    {
        $node = new InNsNode('my-great\\ns');

        ob_start();
        $this->inNsEmitter->emit($node);
        $output = ob_get_clean();

        self::assertStringContainsString(
            '"my-great\\\\ns"',
            $output,
            'The *ns* definition should contain the original unhyphenated namespace',
        );

        self::assertStringNotContainsString(
            '"my_great\\\\ns"',
            $output,
            'The *ns* definition should not contain the munged namespace',
        );
    }

    public function test_in_ns_without_hyphens_is_unchanged(): void
    {
        $node = new InNsNode('app\\module');

        ob_start();
        $this->inNsEmitter->emit($node);
        $output = ob_get_clean();

        self::assertStringContainsString('"app\\\\module"', $output);
    }
}
