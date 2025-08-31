<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReadModel\FnSymbolTuple;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

use function array_slice;
use function count;

final class FnSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation("'fn requires at least one argument", $list);
        }

        $second = $list->get(1);
        if ($second instanceof PersistentVectorInterface) {
            return $this->analyzeSingle($list, $env);
        }

        if (!($second instanceof PersistentListInterface)) {
            throw AnalyzerException::withLocation("Second argument of 'fn must be a vector", $list);
        }

        $fnNodes = [];
        $hasVariadic = false;
        $count = count($list);
        for ($i = 1; $i < $count; ++$i) {
            $clause = $list->get($i);
            if (!($clause instanceof PersistentListInterface)) {
                throw AnalyzerException::withLocation('Invalid fn clause', $list);
            }

            $fnNode = $this->analyzeSingle(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN)->copyLocationFrom($clause),
                    ...$clause->toArray(),
                ])->copyLocationFrom($clause),
                $env,
            );

            if ($fnNode->isVariadic()) {
                if ($hasVariadic) {
                    throw AnalyzerException::withLocation('Only one variadic overload allowed', $clause);
                }

                if ($i !== $count - 1) {
                    throw AnalyzerException::withLocation('Variadic overload must be the last one', $clause);
                }

                $hasVariadic = true;
            }

            $fnNodes[] = $fnNode;
        }

        return new MultiFnNode($env, $fnNodes, $list->getStartLocation());
    }

    private function analyzeSingle(PersistentListInterface $list, NodeEnvironmentInterface $env): FnNode
    {
        $this->verifyArguments($list);

        $fnSymbolTuple = FnSymbolTuple::createWithTuple($list);
        $recurFrame = new RecurFrame($fnSymbolTuple->params());

        return new FnNode(
            $env,
            $fnSymbolTuple->params(),
            $this->analyzeBody($fnSymbolTuple, $recurFrame, $env),
            $this->buildUsesFromEnv($env, $fnSymbolTuple),
            $fnSymbolTuple->isVariadic(),
            $recurFrame->isActive(),
            $list->getStartLocation(),
        );
    }

    private function verifyArguments(PersistentListInterface $list): void
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation("'fn requires at least one argument", $list);
        }

        if (!($list->get(1) instanceof PersistentVectorInterface)) {
            throw AnalyzerException::withLocation("Second argument of 'fn must be a vector", $list);
        }
    }

    private function analyzeBody(FnSymbolTuple $fnSymbolTuple, RecurFrame $recurFrame, NodeEnvironmentInterface $env): AbstractNode
    {
        $listBody = $fnSymbolTuple->parentListBody();

        [$preConditions, $postConditions, $listBody] = $this->extractPrePostConditions($listBody);

        $body = $fnSymbolTuple->lets() === []
            ? $this->createDoTupleWithBody($listBody)
            : $this->createLetTupleWithBody($fnSymbolTuple, $listBody);

        $body = $this->wrapWithPreAndPostConditions($body, $preConditions, $postConditions);

        $bodyEnv = $env
            ->withMergedLocals($fnSymbolTuple->params())
            ->withReturnContext()
            ->withAddedRecurFrame($recurFrame);

        return $this->analyzer->analyze($body, $bodyEnv);
    }

    /**
     * @param array<int, mixed> $body
     */
    private function createDoTupleWithBody(array $body): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create(Symbol::NAME_DO))->copyLocationFrom($body),
            ...$body,
        ])->copyLocationFrom($body);
    }

    /**
     * @param array<int, mixed> $listBody
     */
    private function createLetTupleWithBody(FnSymbolTuple $fnSymbolTuple, array $listBody): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create(Symbol::NAME_LET))->copyLocationFrom($listBody),
            Phel::vector($fnSymbolTuple->lets())->copyLocationFrom($listBody),
            ...$listBody,
        ])->copyLocationFrom($listBody);
    }

    private function buildUsesFromEnv(NodeEnvironmentInterface $env, FnSymbolTuple $fnSymbolTuple): array
    {
        return array_values(array_diff($env->getLocals(), $fnSymbolTuple->params()));
    }

    /**
     * @param array<int, mixed> $listBody
     *
     * @return array{0: list<mixed>, 1: list<mixed>, 2: array<int, mixed>}
     */
    private function extractPrePostConditions(array $listBody): array
    {
        $pre = [];
        $post = [];

        if (
            $listBody !== []
            && $listBody[0] instanceof PersistentMapInterface
            && (
                $listBody[0]->find(Phel::keyword('pre')) instanceof PersistentVectorInterface
                || $listBody[0]->find(Phel::keyword('post')) instanceof PersistentVectorInterface
            )
        ) {
            $map = $listBody[0];

            $preVec = $map->find(Phel::keyword('pre'));
            if ($preVec instanceof PersistentVectorInterface) {
                foreach ($preVec->getIterator() as $p) {
                    $pre[] = $p;
                }
            }

            $postVec = $map->find(Phel::keyword('post'));
            if ($postVec instanceof PersistentVectorInterface) {
                foreach ($postVec->getIterator() as $p) {
                    $post[] = $p;
                }
            }

            $listBody = array_slice($listBody, 1);
        }

        return [$pre, $post, $listBody];
    }

    /**
     * @param list<mixed> $pre
     * @param list<mixed> $post
     */
    private function wrapWithPreAndPostConditions(
        PersistentListInterface $body,
        array $pre,
        array $post,
    ): PersistentListInterface {
        if ($pre === [] && $post === []) {
            return $body;
        }

        // When the body starts with a `let` form (e.g. due to parameter
        // destructuring) the pre/post conditions must be evaluated inside the
        // `let` body so that destructured names are available in the
        // conditions. Therefore, unwrap the `let`, apply the conditions to the
        // inner body and wrap it again.
        $first = $body->get(0);
        if ($first instanceof Symbol && $first->getName() === Symbol::NAME_LET) {
            /** @var PersistentVectorInterface $bindings */
            $bindings = $body->get(1);
            $innerBodyExpressions = array_slice($body->toArray(), 2);
            $innerBody = $this->createDoTupleWithBody($innerBodyExpressions);
            $wrappedInnerBody = $this->wrapWithPreAndPostConditions($innerBody, $pre, $post);

            return Phel::list([
                Symbol::create(Symbol::NAME_LET)->copyLocationFrom($body),
                $bindings,
                $wrappedInnerBody,
            ])->copyLocationFrom($body);
        }

        $preForms = [];
        foreach ($pre as $p) {
            $preForms[] = $this->createAssertForm($p, $p);
        }

        if ($post === []) {
            return Phel::list([
                Symbol::create(Symbol::NAME_DO)->copyLocationFrom($body),
                ...$preForms,
                $body,
            ])->copyLocationFrom($body);
        }

        $resultSym = Symbol::gen()->copyLocationFrom($body);

        $postForms = [];
        foreach ($post as $p) {
            $postForms[] = $this->createAssertForm(
                $this->replacePercent($p, $resultSym),
                $p,
            );
        }

        $let = Phel::list([
            Symbol::create(Symbol::NAME_LET)->copyLocationFrom($body),
            Phel::vector([$resultSym, $body])->copyLocationFrom($body),
            ...$postForms,
            $resultSym,
        ])->copyLocationFrom($body);

        return Phel::list([
            Symbol::create(Symbol::NAME_DO)->copyLocationFrom($body),
            ...$preForms,
            $let,
        ])->copyLocationFrom($body);
    }

    private function createAssertForm(mixed $form, mixed $formForMessage): PersistentListInterface
    {
        $message = Phel::list([
            Symbol::create('php/.'),
            'Assert failed: ',
            Phel::list([
                Symbol::create('print-str'),
                Phel::list([
                    Symbol::create('quote'),
                    $formForMessage,
                ])->copyLocationFrom($formForMessage),
            ])->copyLocationFrom($formForMessage),
        ])->copyLocationFrom($formForMessage);

        return Phel::list([
            Symbol::create('php/assert')->copyLocationFrom($form),
            $form,
            $message,
        ])->copyLocationFrom($form);
    }

    private function replacePercent(mixed $form, Symbol $sym): mixed
    {
        if ($form instanceof Symbol) {
            if ($form->getName() === Symbol::NAME_DOLLAR) {
                return $sym;
            }

            return $form;
        }

        if ($form instanceof PersistentListInterface) {
            $items = [];
            foreach ($form->getIterator() as $item) {
                $items[] = $this->replacePercent($item, $sym);
            }

            return Phel::list($items)->copyLocationFrom($form);
        }

        if ($form instanceof PersistentVectorInterface) {
            $items = [];
            foreach ($form->getIterator() as $item) {
                $items[] = $this->replacePercent($item, $sym);
            }

            return Phel::vector($items)->copyLocationFrom($form);
        }

        if ($form instanceof PersistentMapInterface) {
            $kvs = [];
            foreach ($form->getIterator() as $k => $v) {
                $kvs[] = $this->replacePercent($k, $sym);
                $kvs[] = $this->replacePercent($v, $sym);
            }

            return Phel::map(...$kvs)->copyLocationFrom($form);
        }

        return $form;
    }
}
