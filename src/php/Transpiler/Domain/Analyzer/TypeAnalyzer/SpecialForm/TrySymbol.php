<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Transpiler\Domain\Analyzer\Ast\TryNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

final class TrySymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): TryNode
    {
        $state = 'start';
        $body = [];
        $catches = [];
        /** @var PersistentListInterface|null $finally */
        $finally = null;
        for ($forms = $list->cdr(); $forms instanceof PersistentListInterface; $forms = $forms->cdr()) {
            /** @var mixed $form */
            $form = $forms->first();

            switch ($state) {
                case 'start':
                    if ($this->isSymWithName($form->get(0), 'catch')) {
                        $state = 'catches';
                        $catches[] = $form;
                    } elseif ($this->isSymWithName($form->get(0), 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        $body[] = $form;
                    }

                    break;

                case 'catches':
                    if ($this->isSymWithName($form->get(0), 'catch')) {
                        $catches[] = $form;
                    } elseif ($this->isSymWithName($form->get(0), 'finally')) {
                        $state = 'done';
                        $finally = $form;
                    } else {
                        throw AnalyzerException::withLocation("Invalid 'try form", $list);
                    }

                    break;

                case 'done':
                    throw AnalyzerException::withLocation("Unexpected form after 'finally", $list);

                default:
                    throw AnalyzerException::withLocation("Unexpected parser state in 'try", $list);
            }
        }

        if ($finally instanceof PersistentListInterface) {
            /** @psalm-suppress InvalidOperand */
            $finally = TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_DO),
                ...$finally->rest(),
            ])->copyLocationFrom($finally);

            $finally = $this->analyzer->analyze(
                $finally,
                $env->withStatementContext()->withDisallowRecurFrame(),
            );
        }

        $catchCtx = $env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)
            ? NodeEnvironment::CONTEXT_RETURN
            : $env->getContext();

        $catchNodes = [];
        /** @var PersistentListInterface $catch */
        foreach ($catches as $catch) {
            $type = $catch->get(1);
            $name = $catch->get(2);

            if (!($type instanceof Symbol)) {
                throw AnalyzerException::withLocation("First argument of 'catch must be a Symbol", $catch);
            }

            if (!($name instanceof Symbol)) {
                throw AnalyzerException::withLocation("Second argument of 'catch must be a Symbol", $catch);
            }

            $resolvedType = $this->analyzer->resolve($type, $env);

            if (!$resolvedType instanceof AbstractNode) {
                throw AnalyzerException::withLocation('Can not resolve type ' . $type->getName(), $catch);
            }

            $exprs = [Symbol::create(Symbol::NAME_DO), ...$catch->rest()->rest()->rest()->toArray()];

            $catchBody = $this->analyzer->analyze(
                TypeFactory::getInstance()->persistentListFromArray($exprs),
                $env->withContext($catchCtx)
                    ->withMergedLocals([$name])
                    ->withDisallowRecurFrame(),
            );

            $catchNodes[] = new CatchNode(
                $env,
                $resolvedType,
                $name,
                $catchBody,
                $catch->getStartLocation(),
            );
        }

        $body = $this->analyzer->analyze(
            TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_DO), ...$body]),
            $env->withContext($catchNodes !== [] || $finally ? $catchCtx : $env->getContext())
                ->withDisallowRecurFrame(),
        );

        return new TryNode(
            $env,
            $body,
            $catchNodes,
            $finally,
            $list->getStartLocation(),
        );
    }

    private function isSymWithName(mixed $x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }
}
