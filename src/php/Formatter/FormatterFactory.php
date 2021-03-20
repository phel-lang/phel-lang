<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\AbstractFactory;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\Formatter\Formatter;
use Phel\Formatter\Formatter\FormatterInterface;
use Phel\Formatter\Rules\Indenter\BlockIndenter;
use Phel\Formatter\Rules\Indenter\InnerIndenter;
use Phel\Formatter\Rules\IndentRule;
use Phel\Formatter\Rules\RemoveSurroundingWhitespaceRule;
use Phel\Formatter\Rules\RemoveTrailingWhitespaceRule;
use Phel\Formatter\Rules\UnindentRule;

final class FormatterFactory extends AbstractFactory
{
    public function createFormatter(): FormatterInterface
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

    public function createRemoveSurroundingWhitespaceRule(): RemoveSurroundingWhitespaceRule
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

    private function getFacadeCompiler(): CompilerFacade
    {
        return $this->getProvidedDependency(FormatterDependencyProvider::FACADE_COMPILER);
    }
}
