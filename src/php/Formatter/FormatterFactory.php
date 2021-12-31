<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractFactory;
use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\Command\FormatCommand;
use Phel\Formatter\Domain\Formatter;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\Domain\PathFilterInterface;
use Phel\Formatter\Domain\PhelPathFilter;
use Phel\Formatter\Domain\Rules\Indenter\BlockIndenter;
use Phel\Formatter\Domain\Rules\Indenter\InnerIndenter;
use Phel\Formatter\Domain\Rules\IndentRule;
use Phel\Formatter\Domain\Rules\RemoveSurroundingWhitespaceRule;
use Phel\Formatter\Domain\Rules\RemoveTrailingWhitespaceRule;
use Phel\Formatter\Domain\Rules\UnindentRule;

final class FormatterFactory extends AbstractFactory
{
    public function createFormatCommand(): FormatCommand
    {
        return new FormatCommand(
            $this->getCommandFacade(),
            $this->createFormatter(),
            $this->createPathFilter()
        );
    }

    private function createFormatter(): FormatterInterface
    {
        return new Formatter(
            $this->getFacadeCompiler(),
            [
                $this->createRemoveSurroundingWhitespaceRule(),
                $this->createUnindentRule(),
                $this->createIndentRule(),
                $this->createRemoveTrailingWhitespaceRule(),
            ]
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

    private function createIndentRule(): IndentRule
    {
        return new IndentRule([
            new InnerIndenter('def', 0),
            new InnerIndenter('def-', 0),
            new InnerIndenter('defn', 0),
            new InnerIndenter('defn-', 0),
            new InnerIndenter('defmacro', 0),
            new InnerIndenter('defmacro-', 0),
            new InnerIndenter('deftest', 0),
            new InnerIndenter('fn', 0),

            new BlockIndenter('catch', 2),
            new BlockIndenter('do', 0),
            new BlockIndenter('if', 1),
            new BlockIndenter('if-not', 1),
            new BlockIndenter('foreach', 1),
            new BlockIndenter('for', 1),
            new BlockIndenter('dofor', 1),
            new BlockIndenter('let', 1),
            new BlockIndenter('ns', 1),
            new BlockIndenter('loop', 1),
            new BlockIndenter('case', 1),
            new BlockIndenter('cond', 0),
            new BlockIndenter('try', 0),
            new BlockIndenter('finally', 0),
            new BlockIndenter('when', 1),
            new BlockIndenter('when-not', 1),
        ]);
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
        return $this->getProvidedDependency(FormatterDependencyProvider::FACADE_COMPILER);
    }

    private function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(FormatterDependencyProvider::FACADE_COMMAND);
    }
}
