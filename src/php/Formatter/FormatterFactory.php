<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractFactory;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\Application\Formatter;
use Phel\Formatter\Application\PathsFormatter;
use Phel\Formatter\Application\PhelPathFilter;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\Domain\IO\FileIoInterface;
use Phel\Formatter\Domain\PathFilterInterface;
use Phel\Formatter\Domain\Rules\AlignPairsRule;
use Phel\Formatter\Domain\Rules\Indenter\BlockIndenter;
use Phel\Formatter\Domain\Rules\Indenter\InnerIndenter;
use Phel\Formatter\Domain\Rules\IndentRule;
use Phel\Formatter\Domain\Rules\RemoveConsecutiveBlankLinesRule;
use Phel\Formatter\Domain\Rules\RemoveSurroundingWhitespaceRule;
use Phel\Formatter\Domain\Rules\RemoveTrailingWhitespaceRule;
use Phel\Formatter\Domain\Rules\UnindentRule;
use Phel\Formatter\Infrastructure\IO\SystemFileIo;
use Phel\Shared\Facade\CommandFacadeInterface;

/**
 * @extends AbstractFactory<FormatterConfig>
 */
final class FormatterFactory extends AbstractFactory
{
    /**
     * Definition forms whose body is indented two spaces under the head line.
     *
     * @var list<string>
     */
    private const array INNER_INDENT_SYMBOLS = [
        'def', 'def-', 'defn', 'defn-', 'defmacro', 'defmacro-', 'deftest', 'fn',
        'defstruct', 'defrecord', 'definterface', 'defexception', 'defenum',
        'defprotocol', 'defmulti', 'defmethod', 'defonce', 'reify',
    ];

    /**
     * Block forms mapped to the number of leading arguments before the body
     * (the body switches to a two-space indent once it starts a line).
     *
     * @var array<string, int>
     */
    private const array BLOCK_INDENT_SYMBOLS = [
        'do' => 0, 'cond' => 0, 'try' => 0, 'finally' => 0,
        'with-output-buffer' => 0, 'delay' => 0, 'lazy-seq' => 0,
        'if' => 1, 'if-not' => 1, 'foreach' => 1, 'for' => 1, 'dofor' => 1,
        'let' => 1, 'ns' => 1, 'loop' => 1, 'case' => 1, 'when' => 1,
        'when-not' => 1, 'when-let' => 1, 'when-some' => 1, 'if-let' => 1,
        'if-some' => 1, 'binding' => 1, 'when-first' => 1, 'doseq' => 1,
        'dotimes' => 1, 'letfn' => 1, 'with-redefs' => 1, 'with-bindings' => 1,
        'extend-type' => 1, 'extend-protocol' => 1,
        'catch' => 2, 'condp' => 2,
    ];

    public function createPathsFormatter(): PathsFormatter
    {
        return new PathsFormatter(
            $this->getCommandFacade(),
            $this->createFormatter(),
            $this->createPathFilter(),
            $this->createFileIo(),
        );
    }

    public function createFormatter(): FormatterInterface
    {
        return new Formatter(
            $this->getFacadeCompiler(),
            [
                $this->createRemoveSurroundingWhitespaceRule(),
                $this->createUnindentRule(),
                $this->createRemoveConsecutiveBlankLinesRule(),
                $this->createIndentRule(),
                $this->createAlignPairsRule(),
                $this->createRemoveTrailingWhitespaceRule(),
            ],
        );
    }

    private function createRemoveSurroundingWhitespaceRule(): RemoveSurroundingWhitespaceRule
    {
        return new RemoveSurroundingWhitespaceRule();
    }

    private function createUnindentRule(): UnindentRule
    {
        return new UnindentRule();
    }

    private function createRemoveConsecutiveBlankLinesRule(): RemoveConsecutiveBlankLinesRule
    {
        return new RemoveConsecutiveBlankLinesRule();
    }

    private function createIndentRule(): IndentRule
    {
        $inner = array_map(
            static fn(string $symbol): InnerIndenter => new InnerIndenter($symbol, 0),
            self::INNER_INDENT_SYMBOLS,
        );

        $block = [];
        foreach (self::BLOCK_INDENT_SYMBOLS as $symbol => $index) {
            $block[] = new BlockIndenter($symbol, $index);
        }

        return new IndentRule([...$inner, ...$block]);
    }

    private function createAlignPairsRule(): AlignPairsRule
    {
        return new AlignPairsRule();
    }

    private function createRemoveTrailingWhitespaceRule(): RemoveTrailingWhitespaceRule
    {
        return new RemoveTrailingWhitespaceRule();
    }

    private function createPathFilter(): PathFilterInterface
    {
        return new PhelPathFilter();
    }

    private function getFacadeCompiler(): CompilerFacade
    {
        /** @var CompilerFacade $facade */
        $facade = $this->getProvidedDependency(FormatterProvider::FACADE_COMPILER);

        return $facade;
    }

    private function getCommandFacade(): CommandFacadeInterface
    {
        /** @var CommandFacadeInterface $facade */
        $facade = $this->getProvidedDependency(FormatterProvider::FACADE_COMMAND);

        return $facade;
    }

    private function createFileIo(): FileIoInterface
    {
        return new SystemFileIo();
    }
}
