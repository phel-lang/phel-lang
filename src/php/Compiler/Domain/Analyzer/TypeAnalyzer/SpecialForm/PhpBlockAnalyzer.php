<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\DefStructInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\MungeInterface;

/**
 * Handles the `:php` block of a `defstruct`/`defenum`: a sequence of bare PHP
 * methods emitted directly on the generated class with no backing interface.
 * This is the way to declare plain helper methods and PHP magic methods
 * (`__invoke`, `__toString`, `__get`, ...) that belong to no PHP interface.
 * The struct-specific `__invoke` arity guard is skipped for non-struct hosts.
 */
final readonly class PhpBlockAnalyzer
{
    private const string MARKER = 'php';

    private const string INVOKE = '__invoke';

    private const string VARIADIC = '&';

    public function __construct(
        private MungeInterface $munge,
        private MethodBodyAnalyzer $methodBodyAnalyzer,
    ) {}

    public function isMarker(mixed $form): bool
    {
        return $form instanceof Keyword
            && $form->getNamespace() === null
            && $form->getName() === self::MARKER;
    }

    /**
     * Consumes every consecutive method form following the `:php` marker and
     * returns the bare-method interface together with the cursor pointing at
     * the last consumed form, so the caller's `cdr()` step resumes correctly.
     *
     * @param PersistentListInterface<mixed> $forms cursor positioned on `:php`
     *
     * @return array{0: DefStructInterface, 1: PersistentListInterface<mixed>}
     */
    public function analyze(PersistentListInterface $forms, NodeEnvironmentInterface $env, bool $enforceInvokeArity = true): array
    {
        $methods = [];
        while (($next = $forms->cdr()) instanceof PersistentListInterface
            && $next->first() instanceof PersistentListInterface
        ) {
            $forms = $next;
            /** @var PersistentListInterface<mixed> $methodForm */
            $methodForm = $forms->first();
            if ($enforceInvokeArity) {
                $this->assertCompatibleInvoke($methodForm);
            }

            $methods[] = $this->methodBodyAnalyzer->analyze($methodForm, $env);
        }

        return [new DefStructInterface('', $methods), $forms];
    }

    /**
     * A struct is a persistent map, which already defines a callable
     * `__invoke(mixed $key)` for key lookup. A user-supplied `__invoke` must
     * keep a PHP-compatible signature, i.e. accept exactly one required call
     * argument or be variadic; otherwise PHP raises an uncatchable fatal at
     * class-declaration time. Surface that as a clear Phel error instead.
     *
     * @param PersistentListInterface<mixed> $methodForm
     */
    private function assertCompatibleInvoke(PersistentListInterface $methodForm): void
    {
        $name = $methodForm->get(0);
        if (!$name instanceof Symbol || $this->munge->encode($name->getName()) !== self::INVOKE) {
            return;
        }

        $arguments = $methodForm->get(1);
        if (!$arguments instanceof PersistentVectorInterface) {
            return;
        }

        $variadic = false;
        $required = 0;
        // Skip the leading `this`; count required call args until the `&` tail.
        foreach ($arguments as $index => $argument) {
            if ($index === 0) {
                continue;
            }

            if ($argument instanceof Symbol && $argument->getName() === self::VARIADIC) {
                $variadic = true;
                break;
            }

            ++$required;
        }

        $compatible = $required === 1 || ($required === 0 && $variadic);
        if (!$compatible) {
            throw AnalyzerException::withLocation(
                "A struct's '__invoke' must take exactly one call argument or be variadic "
                . '(e.g. [this x] or [this & xs]), because a struct is already callable as a map. '
                . 'To accept a call with no meaningful argument, use a variadic tail and ignore it: [this & _]. Got '
                . $required . ' required argument(s).',
                $methodForm,
            );
        }
    }
}
