<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Evaluator\Exceptions;

use Exception;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\Fnable;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

class CompiledCodeIsMalformedExceptionTest extends TestCase
{
    public function test_default_error(): void
    {
        $originalErrorMsg = self::originalErrorTrace('Any CompiledCodeIsMalformedException default error');
        $throwable = new Exception($originalErrorMsg);

        $exception = CompiledCodeIsMalformedException::fromThrowable(
            $throwable,
            $this->stubAbstractNode('custom-fn'),
        );

        self::assertSame($originalErrorMsg, $exception->getMessage());
    }

    public function test_wrong_args_number(): void
    {
        $throwable = new class() extends Exception {
            public function __construct()
            {
                $msg = CompiledCodeIsMalformedExceptionTest::originalErrorTrace(
                    sprintf(
                        'Too few arguments to function Phel\Lang\AbstractFn@anonymous::__invoke(), 2 passed in %s on line 4 and exactly 3 expected',
                        __DIR__ . '/T/__phel-unique-id',
                    ),
                );
                parent::__construct($msg);
            }
        };

        $exception = CompiledCodeIsMalformedException::fromThrowable(
            $throwable,
            $this->stubAbstractNode('custom-fn'),
        );

        $expected = <<<'EOF'
Too few arguments to function starting from `custom-fn`, 2 passed in and exactly 3 expected
> php(location) : __phel-unique-id

return (\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "reduce"))(\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "+"), (\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "map"))(\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "*"), \Phel\Lang\Registry::getInstance()->getDefinition("example\\local", "array1"), \Phel\Lang\Registry::getInstance()->getDefinition("example\\local", "array2")));
EOF;
        self::assertSame($expected, $exception->getMessage());
    }

    public function test_wrong_args_number_with_location(): void
    {
        $throwable = new class() extends Exception {
            public function __construct()
            {
                $msg = CompiledCodeIsMalformedExceptionTest::originalErrorTrace(
                    sprintf(
                        'Too few arguments to function Phel\Lang\AbstractFn@anonymous::__invoke(), 2 passed in %s on line 4 and exactly 3 expected',
                        __DIR__ . '/T/__phel-unique-id',
                    ),
                );
                parent::__construct($msg);
            }
        };

        $exception = CompiledCodeIsMalformedException::fromThrowable(
            $throwable,
            $this->stubAbstractNode('custom-fn', new SourceLocation('/file/path.phel', 20, 35)),
        );

        $expected = <<<'EOF'
Too few arguments to function starting from `custom-fn`, 2 passed in and exactly 3 expected
> phel(location): /file/path.phel:20
> php(location) : __phel-unique-id

return (\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "reduce"))(\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "+"), (\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "map"))(\Phel\Lang\Registry::getInstance()->getDefinition("phel\\core", "*"), \Phel\Lang\Registry::getInstance()->getDefinition("example\\local", "array1"), \Phel\Lang\Registry::getInstance()->getDefinition("example\\local", "array2")));
EOF;
        self::assertSame($expected, $exception->getMessage());
    }

    public function stubAbstractNode(string $name, ?SourceLocation $location = null): AbstractNode
    {
        $env = $this->createStub(NodeEnvironmentInterface::class);

        return new class($name, $env, $location) extends AbstractNode implements Fnable {
            public function __construct(
                private readonly string $name,
                NodeEnvironmentInterface $env,
                ?SourceLocation $location = null,
            ) {
                parent::__construct($env, $location);
            }

            public function getFn(): LocalVarNode
            {
                return new LocalVarNode(
                    $this->getEnv(),
                    new Symbol('', $this->name),
                );
            }

            public function getArgs(): array
            {
                return [];
            }
        };
    }

    public static function originalErrorTrace(string $msg): string
    {
        return <<<OUT
Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException: {$msg}
in /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Evaluator/Exceptions/CompiledCodeIsMalformedException.php:14 (gen: /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Evaluator/Exceptions/CompiledCodeIsMalformedException.php:14)

#0 /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Evaluator/RequireEvaluator.php(42): Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException::fromThrowable(Object(ArgumentCountError))
#1 /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Compiler/EvalCompiler.php(110): Phel\Compiler\Domain\Evaluator\RequireEvaluator->eval('// string
// ;;...')
#2 /Users/.../phel-lang/phel-lang/src/php/Compiler/Domain/Compiler/EvalCompiler.php(69): Phel\Compiler\Domain\Compiler\EvalCompiler->evalNode(Object(Phel\Compiler\Domain\Analyzer\Ast\CallNode), Object(Phel\Compiler\Infrastructure\CompileOptions))
#3 etc...
OUT;
    }
}
