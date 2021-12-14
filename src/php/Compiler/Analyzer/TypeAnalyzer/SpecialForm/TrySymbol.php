<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\CatchNode;
use Phel\Compiler\Analyzer\Ast\TryNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

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
        for ($forms = $list->cdr(); $forms != null; $forms = $forms->cdr()) {
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

        if ($finally) {
            $finally = TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_DO),
                ...$finally->rest(),
            ])->copyLocationFrom($finally);
            $finally = $this->analyzer->analyze(
                $finally,
                $env->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)->withDisallowRecurFrame()
            );
        }

        $catchCtx = $env->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION ? NodeEnvironmentInterface::CONTEXT_RETURN : $env->getContext();
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

            if (!$resolvedType) {
                throw AnalyzerException::withLocation('Can not resolve type ' . $type->getName(), $catch);
            }

            $exprs = [Symbol::create(Symbol::NAME_DO), ...$catch->rest()->rest()->rest()->toArray()];

            $catchBody = $this->analyzer->analyze(
                TypeFactory::getInstance()->persistentListFromArray($exprs),
                $env->withContext($catchCtx)
                    ->withMergedLocals([$name])
                    ->withDisallowRecurFrame()
            );

            $catchNodes[] = new CatchNode(
                $env,
                $resolvedType,
                $name,
                $catchBody,
                $catch->getStartLocation()
            );
        }

        $body = $this->analyzer->analyze(
            TypeFactory::getInstance()->persistentListFromArray(array_merge([Symbol::create(Symbol::NAME_DO)], $body)),
            $env->withContext(count($catchNodes) > 0 || $finally ? $catchCtx : $env->getContext())
                ->withDisallowRecurFrame()
        );

        return new TryNode(
            $env,
            $body,
            $catchNodes,
            $finally,
            $list->getStartLocation()
        );
    }

    /**
     * @param mixed $x
     */
    private function isSymWithName($x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }
}
