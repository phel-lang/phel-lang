<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\WithAnalyzer;
use Phel\Compiler\Ast\CatchNode;
use Phel\Compiler\Ast\TryNode;
use Phel\Compiler\NodeEnvironment;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class TrySymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): TryNode
    {
        $tupleCount = count($tuple);
        $state = 'start';
        $body = [];
        $catches = [];
        /** @var Tuple|null $finally */
        $finally = null;
        for ($i = 1; $i < $tupleCount; $i++) {
            /** @var mixed $form */
            $form = $tuple[$i];

            switch ($state) {
                case 'start':
                    if ($this->isSymWithName($form[0], 'catch')) {
                        $state = 'catches';
                        $catches[] = $form;
                    } elseif ($this->isSymWithName($form[0], 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        $body[] = $form;
                    }
                    break;

                case 'catches':
                    if ($this->isSymWithName($form[0], 'catch')) {
                        $catches[] = $form;
                    } elseif ($this->isSymWithName($form[0], 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        throw AnalyzerException::withLocation("Invalid 'try form", $tuple);
                    }
                    break;

                case 'done':
                   throw AnalyzerException::withLocation("Unexpected form after 'finally", $tuple);

                default:
                   throw AnalyzerException::withLocation("Unexpected parser state in 'try", $tuple);
            }
        }

        if ($finally) {
            $finally = $finally->update(0, Symbol::create(Symbol::NAME_DO));
            $finally = $this->analyzer->analyze(
                $finally,
                $env->withContext(NodeEnvironment::CONTEXT_STATEMENT)->withDisallowRecurFrame()
            );
        }

        $catchCtx = $env->getContext() === NodeEnvironment::CONTEXT_EXPRESSION ? NodeEnvironment::CONTEXT_RETURN : $env->getContext();
        $catchNodes = [];
        /** @var Tuple $catch */
        foreach ($catches as $catch) {
            [$_, $type, $name] = $catch;

            if (!($type instanceof Symbol)) {
                throw AnalyzerException::withLocation("First argument of 'catch must be a Symbol", $catch);
            }

            if (!($name instanceof Symbol)) {
                throw AnalyzerException::withLocation("Second argument of 'catch must be a Symbol", $catch);
            }

            $exprs = [Symbol::create(Symbol::NAME_DO)];
            $catchCount = count($catch);
            for ($i = 3; $i < $catchCount; $i++) {
                $exprs[] = $catch[$i];
            }

            $catchBody = $this->analyzer->analyze(
                new Tuple($exprs),
                $env->withContext($catchCtx)
                    ->withMergedLocals([$name])
                    ->withDisallowRecurFrame()
            );

            $catchNodes[] = new CatchNode(
                $env,
                $type,
                $name,
                $catchBody,
                $catch->getStartLocation()
            );
        }

        $body = $this->analyzer->analyze(
            new Tuple(array_merge([Symbol::create(Symbol::NAME_DO)], $body)),
            $env->withContext(count($catchNodes) > 0 || $finally ? $catchCtx : $env->getContext())
                ->withDisallowRecurFrame()
        );

        return new TryNode(
            $env,
            $body,
            $catchNodes,
            $finally,
            $tuple->getStartLocation()
        );
    }

    /** @param mixed $x */
    private function isSymWithName($x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }
}
