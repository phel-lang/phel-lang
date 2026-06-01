<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\ConstantScope;
use Phel\Compiler\Domain\Emitter\OutputEmitter\LiteralEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterFactory;
use Phel\Compiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\MungeInterface;
use Phel\Shared\Printer\PrinterInterface;

use function array_pop;
use function array_values;
use function count;
use function end;
use function is_null;
use function strlen;

final class OutputEmitter implements OutputEmitterInterface
{
    private int $indentLevel = 0;

    private int $classScopeDepth = 0;

    /** @var array<int, string> */
    private array $indentCache = [];

    /** @var list<ConstantScope> */
    private array $constantScopes = [];

    public function __construct(
        private readonly bool $enableSourceMaps,
        private readonly NodeEmitterFactory $nodeEmitterFactory,
        private readonly MungeInterface $munge,
        private readonly PrinterInterface $printer,
        private readonly SourceMapState $sourceMapState,
        private readonly OutputEmitterOptions $options,
    ) {}

    public function pushConstantScope(ConstantScope $scope): void
    {
        $this->constantScopes[] = $scope;
    }

    public function popConstantScope(): void
    {
        array_pop($this->constantScopes);
    }

    public function currentConstantScope(): ?ConstantScope
    {
        if ($this->constantScopes === []) {
            return null;
        }

        return end($this->constantScopes);
    }

    /**
     * Opens a constant-cache wrap for `$node` and returns `true` when a
     * slot was reserved for it. Callers must pair a `true` return with a
     * matching {@see emitConstantSlotSuffix()} call after the inner
     * expression has been emitted; a `false` return is a no-op.
     */
    public function emitConstantSlotPrefix(AbstractNode $node, ?SourceLocation $loc = null): bool
    {
        $slot = $this->currentConstantScope()?->lookup($node);
        if ($slot === null) {
            return false;
        }

        $this->emitStr('($__phel_const_' . $slot . ' ??= ', $loc);
        return true;
    }

    public function emitConstantSlotSuffix(?SourceLocation $loc = null): void
    {
        $this->emitStr(')', $loc);
    }

    public function callSlotFor(AbstractNode $node): ?int
    {
        return $this->currentConstantScope()?->lookupCallSlot($node);
    }

    public function getOptions(): OutputEmitterOptions
    {
        return $this->options;
    }

    public function resetIndentLevel(): void
    {
        $this->indentLevel = 0;
    }

    public function resetSourceMapState(): void
    {
        $this->sourceMapState->reset();
    }

    public function getSourceMapState(): SourceMapState
    {
        return $this->sourceMapState;
    }

    public function emitNode(AbstractNode $node): void
    {
        $nodeEmitter = $this->nodeEmitterFactory->createNodeEmitter($this, $node::class);
        $nodeEmitter->emit($node);
    }

    public function captureNodeAsExpression(AbstractNode $node): string
    {
        ob_start();
        try {
            $this->emitNode($node);
        } finally {
            $buf = (string) ob_get_clean();
        }

        // The node's baked-in env may be RETURN (so it emits `return …;`), but
        // the caller splices the chunk into a surrounding expression position
        // (ternary arm, `if (…)` test, native-op fragment), where a `return`
        // prefix or trailing `;` would be invalid PHP. Strip both so the
        // chunk renders as a bare expression.
        $buf = preg_replace('/^return\s+/', '', $buf) ?? '';
        $buf = rtrim($buf);
        if ($buf !== '' && str_ends_with($buf, ';')) {
            return substr($buf, 0, -1);
        }

        return $buf;
    }

    public function emitLine(string $str = '', ?SourceLocation $sl = null): void
    {
        if ($str !== '') {
            $this->emitStr($str, $sl);
        }

        $this->sourceMapState->incGeneratedLines();
        $this->sourceMapState->setGeneratedColumns(0);

        echo PHP_EOL;
    }

    public function emitStr(string $str, ?SourceLocation $sl = null): void
    {
        if ($this->sourceMapState->getGeneratedColumns() === 0) {
            $indent = $this->indentLevel * 2;
            $this->sourceMapState->incGeneratedColumns($indent);
            echo $this->indentCache[$this->indentLevel] ??= str_repeat(' ', $indent);
        }

        if ($this->enableSourceMaps && $sl instanceof SourceLocation) {
            $this->sourceMapState->addMapping([
                'source' => $sl->getFile(),
                'original' => [
                    'line' => $sl->getLine() - 1,
                    'column' => $sl->getColumn(),
                ],
                'generated' => [
                    'line' => $this->sourceMapState->getGeneratedLines(),
                    'column' => $this->sourceMapState->getGeneratedColumns(),
                ],
            ]);
        }

        $this->sourceMapState->incGeneratedColumns(strlen($str));

        echo $str;
    }

    /**
     * @param list<AbstractNode> $nodes
     */
    public function emitArgList(array $nodes, ?SourceLocation $sepLoc, string $sep = ', '): void
    {
        $nodesCount = count($nodes);
        foreach ($nodes as $i => $arg) {
            $this->emitNode($arg);

            if ($i < $nodesCount - 1) {
                $this->emitStr($sep, $sepLoc);
            }
        }
    }

    public function emitContextPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void
    {
        if ($env->isContext(NodeEnvironment::CONTEXT_RETURN)) {
            $this->emitStr('return ', $sl);
        }
    }

    public function emitContextSuffix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void
    {
        if (!$env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->emitStr(';', $sl);
        }
    }

    public function emitFnWrapPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void
    {
        $this->emitStr('(function()', $sl);
        $this->emitUseClauseForWrap($env, $sl);
        $this->emitLine(' {', $sl);
        $this->increaseIndentLevel();
    }

    public function emitPhpVariable(
        Symbol $symbol,
        ?SourceLocation $loc = null,
        bool $asReference = false,
        bool $isVariadic = false,
    ): void {
        if (is_null($loc)) {
            $loc = $symbol->getStartLocation();
        }

        $refPrefix = $asReference ? '&' : '';
        $variadicPrefix = $isVariadic ? '...' : '';
        $this->emitStr($variadicPrefix . $refPrefix . '$' . $this->mungeEncode($symbol->getName()), $loc);
    }

    public function mungeEncode(string $str): string
    {
        return $this->munge->encode($str);
    }

    public function mungeEncodePhpNs(string $str): string
    {
        return $this->munge->encodePhpNs($str);
    }

    public function mungeEncodeRegistryKey(string $str): string
    {
        return $this->munge->encodeRegistryKey($str);
    }

    public function emitFnWrapSuffix(?SourceLocation $sl = null): void
    {
        $this->decreaseIndentLevel();
        $this->emitLine();
        $this->emitStr('})()', $sl);
    }

    public function emitLiteral(mixed $value): void
    {
        new LiteralEmitter($this, $this->printer)->emitLiteral($value);
    }

    public function increaseIndentLevel(): void
    {
        ++$this->indentLevel;
    }

    public function decreaseIndentLevel(): void
    {
        --$this->indentLevel;
    }

    public function enterClassScope(): void
    {
        ++$this->classScopeDepth;
    }

    public function exitClassScope(): void
    {
        --$this->classScopeDepth;
    }

    public function isInsideClassScope(): bool
    {
        return $this->classScopeDepth > 0;
    }

    private function emitUseClauseForWrap(NodeEnvironmentInterface $env, ?SourceLocation $sl): void
    {
        $locals = array_values($env->getLocals());
        $scope = $this->currentConstantScope();
        $constantSlots = $scope?->count() ?? 0;
        $callSlots = $scope?->callSlotCount() ?? 0;

        if ($locals === [] && $constantSlots === 0 && $callSlots === 0) {
            return;
        }

        $this->emitStr(' use(', $sl);

        $first = true;
        foreach ($locals as $local) {
            $first = $this->emitUseSeparator($first, $sl);
            $shadowed = $env->getShadowed($local);
            $this->emitPhpVariable($shadowed instanceof Symbol ? $shadowed : $local, $sl);
        }

        for ($i = 0; $i < $constantSlots; ++$i) {
            $first = $this->emitUseSeparator($first, $sl);
            $this->emitStr('&$__phel_const_' . $i, $sl);
        }

        for ($i = 0; $i < $callSlots; ++$i) {
            $first = $this->emitUseSeparator($first, $sl);
            $this->emitStr('&$__phel_call_' . $i, $sl);
        }

        $this->emitStr(')', $sl);
    }

    private function emitUseSeparator(bool $first, ?SourceLocation $sl): bool
    {
        if (!$first) {
            $this->emitStr(',', $sl);
        }

        return false;
    }
}
