<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use RuntimeException;

use function array_slice;

/**
 * Rewrites a `fn` body's `{:pre [...] :post [...]}` condition map into
 * `assert`-style guard forms.
 *
 * Owns the `$assertsEnabled` flag: when asserts are disabled (or the body
 * carries no conditions) the body is returned unchanged. `:post` conditions
 * reference the return value via `$` (`Symbol::NAME_DOLLAR`), which is
 * replaced by a generated result binding. When the body opens with a `let`
 * (e.g. from parameter destructuring) the conditions are spliced inside the
 * `let` body so destructured names stay in scope.
 */
final readonly class FnPrePostConditionRewriter
{
    public function __construct(
        private bool $assertsEnabled = true,
    ) {}

    /**
     * Strips a leading `{:pre :post}` map off `$listBody`, builds the base
     * body from the remaining forms via `$buildBody`, and wraps it with the
     * pre/post assert forms.
     *
     * @param array<int, mixed>                                           $listBody
     * @param callable(array<int, mixed>): PersistentListInterface<mixed> $buildBody
     *
     * @return PersistentListInterface<mixed>
     */
    public function rewrite(array $listBody, callable $buildBody): PersistentListInterface
    {
        [$pre, $post, $strippedBody] = $this->extractConditions($listBody);
        $body = $buildBody($strippedBody);

        return $this->wrap($body, $pre, $post);
    }

    /**
     * @param array<int, mixed> $listBody
     *
     * @return array{0: list<mixed>, 1: list<mixed>, 2: array<int, mixed>}
     */
    private function extractConditions(array $listBody): array
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
     * @param PersistentListInterface<mixed> $body
     * @param list<mixed>                    $pre
     * @param list<mixed>                    $post
     *
     * @return PersistentListInterface<mixed>
     */
    private function wrap(
        PersistentListInterface $body,
        array $pre,
        array $post,
    ): PersistentListInterface {
        if (!$this->assertsEnabled || ($pre === [] && $post === [])) {
            return $body;
        }

        // When the body starts with a `let` form (e.g. due to parameter
        // destructuring) the pre/post conditions must be evaluated inside the
        // `let` body so that destructured names are available in the
        // conditions. Therefore, unwrap the `let`, apply the conditions to the
        // inner body and wrap it again.
        $first = $body->get(0);
        if ($first instanceof Symbol && $first->getName() === Symbol::NAME_LET) {
            /** @var PersistentVectorInterface<mixed> $bindings */
            $bindings = $body->get(1);
            $innerBodyExpressions = array_slice($body->toArray(), 2);
            $innerBody = $this->wrapInDo($innerBodyExpressions);
            $wrappedInnerBody = $this->wrap($innerBody, $pre, $post);

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

    /**
     * @param array<int, mixed> $body
     *
     * @return PersistentListInterface<mixed>
     */
    private function wrapInDo(array $body): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create(Symbol::NAME_DO))->copyLocationFrom($body),
            ...$body,
        ])->copyLocationFrom($body);
    }

    /**
     * @return PersistentListInterface<mixed>
     */
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

        $exception = Phel::list([
            Symbol::create('php/new')->copyLocationFrom($formForMessage),
            RuntimeException::class,
            $message,
        ])->copyLocationFrom($formForMessage);

        return Phel::list([
            Symbol::create(Symbol::NAME_IF)->copyLocationFrom($form),
            $form,
            null,
            Phel::list([
                Symbol::create(Symbol::NAME_THROW)->copyLocationFrom($form),
                $exception,
            ])->copyLocationFrom($form),
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
