<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

/**
 * (try body (catch Type e handler) (finally cleanup)).
 *
 * Exception handling with optional catch and finally clauses.
 */
final class TrySymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const string STATE_START = 'start';

    private const string STATE_CATCHES = 'catches';

    private const string STATE_DONE = 'done';

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): TryNode
    {
        $parsedTry = $this->parseTryForm($list);
        $catchContext = $this->resolveCatchContext($env);

        $finallyNode = $this->analyzeFinallyBlock($parsedTry['finally'], $env);
        $catchNodes = $this->analyzeCatchBlocks($parsedTry['catches'], $env, $catchContext);
        $bodyNode = $this->analyzeBodyBlock($parsedTry['body'], $env, $catchContext, $catchNodes, $finallyNode);

        return new TryNode(
            $env,
            $bodyNode,
            $catchNodes,
            $finallyNode,
            $list->getStartLocation(),
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return array{
     *     body: list<mixed>,
     *     catches: list<PersistentListInterface<mixed>>,
     *     finally: PersistentListInterface<mixed>|null,
     * }
     */
    private function parseTryForm(PersistentListInterface $list): array
    {
        $state = self::STATE_START;
        $body = [];
        $catches = [];
        $finally = null;

        $forms = $list->cdr();
        for (; $forms instanceof PersistentListInterface; $forms = $forms->cdr()) {
            $form = $forms->first();

            if ($this->isCatchForm($form)) {
                /** @psalm-suppress PossiblyNullArgument -- $form is mixed from first(); null is valid here */
                $state = $this->handleCatchForm($state, $form, $catches, $list);
            } elseif ($this->isFinallyForm($form)) {
                $state = $this->handleFinallyForm($state, $form, $finally, $list);
            } else {
                $this->handleBodyForm($state, $form, $body, $list);
            }
        }

        return [
            'body' => $body,
            'catches' => $catches,
            'finally' => $finally,
        ];
    }

    private function isCatchForm(mixed $form): bool
    {
        return $form instanceof PersistentListInterface
            && $this->isSymWithName($form->get(0), 'catch');
    }

    private function isFinallyForm(mixed $form): bool
    {
        return $form instanceof PersistentListInterface
            && $this->isSymWithName($form->get(0), 'finally');
    }

    /**
     * @param list<PersistentListInterface<mixed>> $catches
     * @param PersistentListInterface<mixed>       $form
     * @param PersistentListInterface<mixed>       $list
     *
     * @param-out non-empty-list<PersistentListInterface<mixed>> $catches
     */
    private function handleCatchForm(string $state, mixed $form, array &$catches, PersistentListInterface $list): string
    {
        if ($state === self::STATE_DONE) {
            throw AnalyzerException::withLocation("Unexpected form after 'finally", $list);
        }

        $catches[] = $form;
        return self::STATE_CATCHES;
    }

    /**
     * @param PersistentListInterface<mixed>|null $finally
     * @param PersistentListInterface<mixed>      $list
     *
     * @param-out PersistentListInterface<mixed> $finally
     */
    private function handleFinallyForm(string $state, mixed $form, ?PersistentListInterface &$finally, PersistentListInterface $list): string
    {
        if ($state === self::STATE_DONE) {
            throw AnalyzerException::withLocation("Unexpected form after 'finally", $list);
        }

        $finally = $form;
        return self::STATE_DONE;
    }

    /**
     * @param list<mixed>                    $body
     * @param PersistentListInterface<mixed> $list
     */
    private function handleBodyForm(string $state, mixed $form, array &$body, PersistentListInterface $list): void
    {
        if ($state === self::STATE_CATCHES) {
            throw AnalyzerException::withLocation("Invalid 'try form", $list);
        }

        if ($state === self::STATE_DONE) {
            throw AnalyzerException::withLocation("Unexpected form after 'finally", $list);
        }

        $body[] = $form;
    }

    private function resolveCatchContext(NodeEnvironmentInterface $env): string
    {
        return $env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)
            ? NodeEnvironment::CONTEXT_RETURN
            : $env->getContext();
    }

    /**
     * @param PersistentListInterface<mixed>|null $finally
     */
    private function analyzeFinallyBlock(?PersistentListInterface $finally, NodeEnvironmentInterface $env): ?AbstractNode
    {
        if (!$finally instanceof PersistentListInterface) {
            return null;
        }

        /** @var PersistentListInterface<mixed> $rest */
        $rest = $finally->rest();
        /** @psalm-suppress InvalidOperand */
        $finallyList = Phel::list([
            Symbol::create(Symbol::NAME_DO),
            ...$rest,
        ])->copyLocationFrom($finally);

        return $this->analyzer->analyze(
            $finallyList,
            $env->withStatementContext()->withDisallowRecurFrame(),
        );
    }

    /**
     * @param list<PersistentListInterface<mixed>> $catches
     *
     * @return list<CatchNode>
     */
    private function analyzeCatchBlocks(array $catches, NodeEnvironmentInterface $env, string $catchContext): array
    {
        $catchNodes = [];

        foreach ($catches as $catch) {
            $catchNodes[] = $this->analyzeSingleCatch($catch, $env, $catchContext);
        }

        return $catchNodes;
    }

    /**
     * @param PersistentListInterface<mixed> $catch
     */
    private function analyzeSingleCatch(PersistentListInterface $catch, NodeEnvironmentInterface $env, string $catchContext): CatchNode
    {
        $type = $catch->get(1);
        $name = $catch->get(2);

        $this->validateCatchArguments($type, $name, $catch);

        $resolvedType = $this->resolveCatchType($type, $env, $catch);
        $catchBody = $this->analyzeCatchBody($catch, $name, $env, $catchContext);

        return new CatchNode(
            $env,
            $resolvedType,
            $name,
            $catchBody,
            $catch->getStartLocation(),
        );
    }

    /**
     * @param PersistentListInterface<mixed> $catch
     */
    private function validateCatchArguments(mixed $type, mixed $name, PersistentListInterface $catch): void
    {
        if (!($type instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("First argument of 'catch", 'Symbol', $type, $catch);
        }

        if (!($name instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("Second argument of 'catch", 'Symbol', $name, $catch);
        }
    }

    /**
     * @param PersistentListInterface<mixed> $catch
     */
    private function resolveCatchType(Symbol $type, NodeEnvironmentInterface $env, PersistentListInterface $catch): AbstractNode
    {
        $resolvedType = $this->analyzer->resolve($type, $env);

        if (!$resolvedType instanceof AbstractNode) {
            throw AnalyzerException::withLocation('Can not resolve type ' . $type->getName(), $catch);
        }

        return $resolvedType;
    }

    /**
     * @param PersistentListInterface<mixed> $catch
     */
    private function analyzeCatchBody(PersistentListInterface $catch, Symbol $name, NodeEnvironmentInterface $env, string $catchContext): AbstractNode
    {
        /** @var PersistentListInterface<mixed> $rest1 */
        $rest1 = $catch->rest();
        /** @var PersistentListInterface<mixed> $rest2 */
        $rest2 = $rest1->rest();
        /** @var PersistentListInterface<mixed> $rest3 */
        $rest3 = $rest2->rest();
        $exprs = [
            Symbol::create(Symbol::NAME_DO),
            ...$rest3->toArray(),
        ];

        return $this->analyzer->analyze(
            Phel::list($exprs),
            $env->withContext($catchContext)
                ->withMergedLocals([$name])
                ->withDisallowRecurFrame(),
        );
    }

    /**
     * @param list<mixed>     $body
     * @param list<CatchNode> $catchNodes
     */
    private function analyzeBodyBlock(array $body, NodeEnvironmentInterface $env, string $catchContext, array $catchNodes, ?AbstractNode $finally): AbstractNode
    {
        $hasCatchOrFinally = $catchNodes !== [] || $finally instanceof AbstractNode;
        $bodyContext = $hasCatchOrFinally ? $catchContext : $env->getContext();

        return $this->analyzer->analyze(
            Phel::list([Symbol::create(Symbol::NAME_DO), ...$body]),
            $env->withContext($bodyContext)->withDisallowRecurFrame(),
        );
    }

    private function isSymWithName(mixed $x, string $name): bool
    {
        return $x instanceof Symbol && $x->getName() === $name;
    }
}
