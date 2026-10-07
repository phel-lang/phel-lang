<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReadModel;

use Phel;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Destructure;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Munge;

use function array_slice;
use function count;
use function in_array;
use function preg_match;

/**
 * @internal
 */
final class FnSymbolTuple
{
    private const string STATE_START = 'start';

    private const string STATE_REST = 'rest';

    private const string STATE_DONE = 'done';

    private const int PARENT_TUPLE_BODY_OFFSET = 2;

    /** PHP refuses these as parameter names: `Cannot re-assign auto-global variable`. */
    private const array SUPERGLOBALS = ['GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV'];

    /** @var list<Symbol> */
    private array $params = [];

    /** @var list<mixed> */
    private array $lets = [];

    private bool $isVariadic = false;

    private bool $hasVariadicForm = false;

    private string $buildParamsState = self::STATE_START;

    /**
     * @param PersistentListInterface<mixed> $parentList
     */
    private function __construct(
        private readonly PersistentListInterface $parentList,
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public static function createWithTuple(PersistentListInterface $list): self
    {
        /** @var PersistentVectorInterface<mixed> $params */
        $params = $list->get(1);
        $self = new self($list);

        foreach ($params->getIterator() as $param) {
            $self->buildParamsByState($param);
        }

        $self->addDummyVariadicSymbol();
        $self->checkAllVariablesStartWithALetterOrUnderscore();
        $self->renameShadowedParams();
        $self->rebindParamsPhpCannotName();

        return $self;
    }

    /**
     * @return list<Symbol>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * @return list<mixed>
     */
    public function lets(): array
    {
        return $this->lets;
    }

    public function isVariadic(): bool
    {
        return $this->isVariadic;
    }

    /**
     * @return list<mixed>
     */
    public function parentListBody(): array
    {
        return array_slice(
            $this->parentList->toArray(),
            self::PARENT_TUPLE_BODY_OFFSET,
        );
    }

    private function addDummyVariadicSymbol(): void
    {
        if (!$this->isVariadic) {
            return;
        }

        if ($this->hasVariadicForm) {
            return;
        }

        $this->params[] = Symbol::gen();
    }

    private function checkAllVariablesStartWithALetterOrUnderscore(): void
    {
        foreach ($this->params as $param) {
            // A qualified param would bind a local that no reference can read
            // back: a namespaced symbol resolves to the global definition.
            if ($param->getNamespace() !== null) {
                throw AnalyzerException::withLocation(
                    "Can't bind qualified name: " . $param->getFullName()
                    . '. Use a bare name, or `' . $param->getName() . '#` for an auto-gensym inside a quasiquote.',
                    $this->parentList,
                    errorCode: ErrorCode::BINDING_ERROR,
                );
            }

            $matchesPattern = preg_match("/^(?:&[a-zA-Z_]|[a-zA-Z_\x80-\xff]).*$/", $param->getName());

            if ($matchesPattern === 0 || $matchesPattern === false) {
                throw AnalyzerException::withLocation(
                    'Variable names must start with a letter or underscore: ' . $param->getName(),
                    $this->parentList,
                    errorCode: ErrorCode::BINDING_ERROR,
                );
            }
        }
    }

    /**
     * A repeated name binds the last argument, as in Clojure. PHP rejects a
     * repeated parameter, so each earlier one gets a name nothing reads.
     */
    private function renameShadowedParams(): void
    {
        $lastIndex = [];
        foreach ($this->params as $i => $param) {
            $lastIndex[$param->getName()] = $i;
        }

        if (count($lastIndex) === count($this->params)) {
            return;
        }

        $params = [];
        foreach ($this->params as $i => $param) {
            $params[] = $lastIndex[$param->getName()] === $i
                ? $param
                : Symbol::gen()->copyLocationFrom($param);
        }

        $this->params = $params;
    }

    /**
     * Two names can munge to one PHP variable (`foo-bar`, `foo_bar`), and a
     * superglobal name cannot be a PHP parameter. Such a param takes a fresh
     * PHP name and is rebound by the body's `let`, which keeps locals apart.
     */
    private function rebindParamsPhpCannotName(): void
    {
        $munge = new Munge();
        $taken = [];
        foreach ($this->params as $i => $param) {
            $phpName = $munge->encode($param->getName());
            if (isset($taken[$phpName]) || in_array($phpName, self::SUPERGLOBALS, true)) {
                $fresh = Symbol::gen()->copyLocationFrom($param);
                $this->params[$i] = $fresh;
                $this->lets[] = $param;
                $this->lets[] = $fresh;

                continue;
            }

            $taken[$phpName] = true;
        }
    }

    private function buildParamsByState(mixed $param): void
    {
        switch ($this->buildParamsState) {
            case self::STATE_START:
                $this->buildParamsStart($param);
                break;
            case self::STATE_REST:
                $this->buildParamsRest($param);
                break;
            case self::STATE_DONE:
                $this->buildParamsDone();
        }
    }

    private function buildParamsStart(mixed $param): void
    {
        if ($param instanceof Symbol) {
            if ($this->isSymWithName($param, '&')) {
                $this->isVariadic = true;
                $this->buildParamsState = self::STATE_REST;
            } elseif ($param->getName() === '_') {
                $this->params[] = Symbol::gen()->copyLocationFrom($param);
            } else {
                $this->params[] = $param;
            }
        } else {
            $tempSym = Symbol::gen()->copyLocationFrom($param);
            $this->params[] = $tempSym;
            $this->lets[] = $param;
            $this->lets[] = $tempSym;
        }
    }

    private function isSymWithName(mixed $x, string $name): bool
    {
        return $x instanceof Symbol
            && $x->getName() === $name;
    }

    private function buildParamsRest(mixed $param): void
    {
        $this->buildParamsState = self::STATE_DONE;
        $this->hasVariadicForm = true;

        if ($this->isSymWithName($param, '_')) {
            $this->params[] = Symbol::gen()->copyLocationFrom($param);
        } elseif ($param instanceof Symbol) {
            $this->params[] = $param;
        } else {
            $tempSym = Symbol::gen()->copyLocationFrom($this->parentList);
            $this->params[] = $tempSym;
            $this->lets[] = $param;
            $this->lets[] = $param instanceof PersistentMapInterface
                ? $this->restKwargs($tempSym)
                : $tempSym;
        }
    }

    /**
     * `(Destructure/restKwargs rest)`: a map pattern after `&` reads the rest
     * arguments as keyword arguments, as Clojure does (#3487).
     *
     * The class symbol carries no location on purpose: it is the compiler's
     * spelling, and a located `\` symbol would announce the separator
     * deprecation against the user's parameters.
     *
     * @return PersistentListInterface<mixed>
     */
    private function restKwargs(Symbol $rest): PersistentListInterface
    {
        return Phel::list([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL)->copyLocationFrom($rest),
            Symbol::create('\\' . Destructure::class),
            Phel::list([Symbol::create('restKwargs')->copyLocationFrom($rest), $rest])->copyLocationFrom($rest),
        ])->copyLocationFrom($rest);
    }

    private function buildParamsDone(): never
    {
        throw AnalyzerException::withLocation(
            'Unsupported parameter form, only one symbol can follow the & parameter',
            $this->parentList,
            errorCode: ErrorCode::BINDING_ERROR,
        );
    }
}
