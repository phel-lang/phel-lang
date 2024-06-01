<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Evaluator\Exceptions;

use Exception;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use PHPUnit\Framework\TestCase;

class CompiledCodeIsMalformedExceptionTest extends TestCase
{
    public function test_readable_error(): void
    {
        $throwable = new class() extends Exception {
            public function __construct()
            {
                $msg = <<<OUT
Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException: Too few arguments to function Phel\Lang\AbstractFn@anonymous::__invoke(), 2 passed in /private/var/folders/qq/dvftwjp527lfdj3kq5nyzhy80000gq/T/__phelIWWVRP on line 4 and exactly 3 expected
in /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Evaluator/Exceptions/CompiledCodeIsMalformedException.php:14 (gen: /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Evaluator/Exceptions/CompiledCodeIsMalformedException.php:14)

#0 /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Evaluator/RequireEvaluator.php(42): Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException::fromThrowable(Object(ArgumentCountError))
#1 /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Compiler/EvalCompiler.php(110): Phel\Compiler\Domain\Evaluator\RequireEvaluator->eval('// string
// ;;...')
#2 /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Compiler/EvalCompiler.php(69): Phel\Compiler\Domain\Compiler\EvalCompiler->evalNode(Object(Phel\Compiler\Domain\Analyzer\Ast\CallNode), Object(Phel\Compiler\Infrastructure\CompileOptions))
#3 etc...
OUT;
                parent::__construct($msg);
            }
        };

        $exception = CompiledCodeIsMalformedException::fromThrowable($throwable);

        $expected = 'Too few arguments to function on line 4, 2 passed in and exactly 3 expected';

        self::assertSame($expected, $exception->getMessage());
    }
}
