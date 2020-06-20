<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer;
use Phel\Ast\CatchNode;
use Phel\Ast\TryNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeTry
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(Tuple $x, NodeEnvironment $env): TryNode
    {
        $tupleCount = count($x);
        $state = 'start';
        $body = [];
        $catches = [];
        /** @var Tuple|null $finally */
        $finally = null;
        for ($i = 1; $i < $tupleCount; $i++) {
            /** @var mixed $form */
            $form = $x[$i];

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
                        throw new AnalyzerException("Invalid 'try form", $x->getStartLocation(), $x->getEndLocation());
                    }
                    break;

                case 'done':
                    throw new AnalyzerException(
                        "Unexpected form after 'finally",
                        $x->getStartLocation(),
                        $x->getEndLocation()
                    );

                default:
                    throw new AnalyzerException(
                        "Unexpected parser state in 'try",
                        $x->getStartLocation(),
                        $x->getEndLocation()
                    );
            }
        }

        if ($finally) {
            $finally = $finally->update(0, new Symbol('do'));
            $finally = $this->analyzer->analyze(
                $finally,
                $env->withContext(NodeEnvironment::CTX_STMT)->withDisallowRecurFrame()
            );
        }

        $catchCtx = $env->getContext() === NodeEnvironment::CTX_EXPR ? NodeEnvironment::CTX_RET : $env->getContext();
        $catchNodes = [];
        /** @var Tuple $catch */
        foreach ($catches as $catch) {
            [$_, $type, $name] = $catch;

            if (!($type instanceof Symbol)) {
                throw new AnalyzerException(
                    "First argument of 'catch must be a Symbol",
                    $catch->getStartLocation(),
                    $catch->getEndLocation()
                );
            }

            if (!($name instanceof Symbol)) {
                throw new AnalyzerException(
                    "Second argument of 'catch must be a Symbol",
                    $catch->getStartLocation(),
                    $catch->getEndLocation()
                );
            }

            $exprs = [new Symbol('do')];
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
            new Tuple(array_merge([new Symbol('do')], $body)),
            $env->withContext(count($catchNodes) > 0 || $finally ? $catchCtx : $env->getContext())
                ->withDisallowRecurFrame()
        );

        return new TryNode(
            $env,
            $body,
            $catchNodes,
            $finally,
            $x->getStartLocation()
        );
    }

    /** @param mixed $x */
    private function isSymWithName($x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }
}
