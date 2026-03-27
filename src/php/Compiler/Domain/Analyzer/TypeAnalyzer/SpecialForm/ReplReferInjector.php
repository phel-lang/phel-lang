<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;

final class ReplReferInjector
{
    public static function injectIfReplMode(AnalyzerInterface $analyzer, string $ns): void
    {
        if (!Phel::getDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::REPL_MODE)) {
            return;
        }

        $replSymbol = Symbol::create('phel\\repl');
        $analyzer->addRequireAlias($ns, Symbol::create('repl'), $replSymbol);
        $analyzer->addRefers(
            $ns,
            [
                Symbol::create('doc'),
                Symbol::create('require'),
                Symbol::create('use'),
                Symbol::create('print-colorful'),
                Symbol::create('println-colorful'),
                Symbol::create('symbol-info'),
                Symbol::create('load-file'),
                Symbol::create('source'),
                Symbol::create('test-ns'),
                Symbol::create('macroexpand-1'),
                Symbol::create('macroexpand'),
            ],
            $replSymbol,
        );
    }
}
