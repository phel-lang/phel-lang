<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

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
            $msg = "Can't call 'recur here. See more: https://phel-lang.org/blog/loop-and-recur";
            /** @psalm-suppress PossiblyNullArgument */
            throw AnalyzerException::withLocation($msg, $list->get(0));
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
