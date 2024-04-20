<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Transpiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

use function count;

final class RecurSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): RecurNode
    {
        if (!$this->isValidRecurTuple($list)) {
            throw AnalyzerException::withLocation("This is not a 'recur.", $list);
        }

        $currentFrame = $env->getCurrentRecurFrame();

        if (!$currentFrame instanceof RecurFrame) {
            /** @psalm-suppress PossiblyNullArgument */
            throw AnalyzerException::withLocation("Can't call 'recur here", $list->get(0));
        }

        if (count($list) - 1 !== count($currentFrame->getParams())) {
            throw AnalyzerException::withLocation(
                "Wrong number of arguments for 'recur. Expected: "
                . count($currentFrame->getParams()) . ' args, got: ' . (count($list) - 1),
                $list,
            );
        }

        $currentFrame->setIsActive(true);

        return new RecurNode(
            $env,
            $currentFrame,
            $this->expressions($list, $env),
            $list->getStartLocation(),
        );
    }

    public function expressions(PersistentListInterface $list, NodeEnvironmentInterface $env): array
    {
        $expressions = [];
        for ($forms = $list->cdr(); $forms != null; $forms = $forms->cdr()) {
            $expressions[] = $this->analyzer->analyze(
                $forms->first(),
                $env->withExpressionContext()->withDisallowRecurFrame(),
            );
        }

        return $expressions;
    }

    private function isValidRecurTuple(PersistentListInterface $list): bool
    {
        return $list->get(0) instanceof Symbol
            && $list->get(0)->getName() === Symbol::NAME_RECUR;
    }
}
