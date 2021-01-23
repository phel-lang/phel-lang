<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Compiler\CompilerFactoryInterface;
use Phel\Formatter\Rules\IndentRule;
use Phel\Formatter\Rules\RemoveSurroundingWhitespaceRule;
use Phel\Formatter\Rules\RemoveTrailingWhitespaceRule;
use Phel\Formatter\Rules\RuleInterface;
use Phel\Formatter\Rules\UnindentRule;

final class FormatterFactory implements FormatterFactoryInterface
{
    private CompilerFactoryInterface $compilerFactory;

    public function __construct(CompilerFactoryInterface $compilerFactory)
    {
        $this->compilerFactory = $compilerFactory;
    }

    public function createFormatter(): FormatterInterface
    {
        return new Formatter(
            $this->compilerFactory->createLexer(),
            $this->compilerFactory->createParser(),
            $this->createRules()
        );
    }

    /**
     * @return RuleInterface[]
     */
    private function createRules(): array
    {
        return [
            new RemoveSurroundingWhitespaceRule(),
            new UnindentRule(),
            new IndentRule(),
            new RemoveTrailingWhitespaceRule(),
        ];
    }
}
