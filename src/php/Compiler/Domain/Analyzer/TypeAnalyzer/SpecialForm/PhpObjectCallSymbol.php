<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;
use function sprintf;

/**
 * (php/-> obj (method args)) / (php/:: Class (method args)).
 *
 * Calls a method on a PHP object or class.
 *
 * @internal
 */
final readonly class PhpObjectCallSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private bool $isStatic,
    ) {}

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpObjectCallNode
    {
        $fnName = $this->isStatic
            ? Symbol::NAME_PHP_OBJECT_STATIC_CALL
            : Symbol::NAME_PHP_OBJECT_CALL;

        if (count($list) < 3) {
            throw AnalyzerException::withLocation("At least two arguments are expected for '" . $fnName, $list);
        }

        $targetExpr = $this->analyzer->analyze(
            $list->get(1),
            $env->withExpressionContext()->withDisallowRecurFrame(),
        );
        $counter = count($list);

        for ($i = 2; $i < $counter; ++$i) {
            $current = $list->get($i);

            if (!$current instanceof PersistentListInterface && !$current instanceof Symbol) {
                throw AnalyzerException::withLocation(
                    sprintf("Argument %d of '%s' must be a List or a Symbol", $i, $fnName),
                    $list,
                );
            }

            if ($current instanceof PersistentListInterface) {
                $methodCall = true;
                $callExpr = $this->callExprForMethodCall($env, $current);
            } else {
                $methodCall = false;
                $callExpr = $this->callExprForPropertyCall($env, $current);
            }

            $isStaticPlace = !$methodCall
                && ($this->isStatic && $i === 2 || $targetExpr instanceof PhpClassNameNode);
            $this->assertSigilNamesAStaticProperty($current, $isStaticPlace, $list);

            $isLast = $i === $counter - 1;
            $nodeEnv = $isLast ? $env : $env->withExpressionContext();

            $targetExpr = new PhpObjectCallNode(
                $nodeEnv,
                $targetExpr,
                $callExpr,
                $this->isStatic && $i === 2,
                $methodCall,
                $current->getStartLocation(),
            );
        }

        /** @var PhpObjectCallNode $targetExpr */
        return $targetExpr;
    }

    /**
     * @param PersistentListInterface<mixed> $segment
     */
    private function callExprForMethodCall(NodeEnvironmentInterface $env, PersistentListInterface $segment): MethodCallNode
    {
        $forms = [];
        for ($rest = $segment->cdr(); $rest !== null; $rest = $rest->cdr()) {
            $forms[] = $rest->first();
        }

        $context = $this->isStatic ? 'php/::' : 'php/->';
        $args = new PhpInteropArgsAnalyzer($this->analyzer)->analyze(
            $forms,
            $env->withExpressionContext()->withDisallowRecurFrame(),
            $context,
            $segment,
        );

        /** @var Symbol $callSymbol */
        $callSymbol = $segment->get(0);
        return new MethodCallNode($env, $callSymbol, $args, $segment->getStartLocation());
    }

    private function callExprForPropertyCall(NodeEnvironmentInterface $env, Symbol $segment): PropertyOrConstantAccessNode
    {
        return new PropertyOrConstantAccessNode($env, $segment, $segment->getStartLocation());
    }

    /**
     * The `$` sigil names a static property, and PHP spells every other member
     * without it. Emitted verbatim anywhere else it reads as a PHP *variable*
     * of that name, which no Phel binding ever defines: `(php/-> o $foo)` used
     * to compile to `$o->$foo` and read `$o->{''}` after two warnings (#2915).
     *
     * @param PersistentListInterface<mixed>|Symbol $segment
     * @param PersistentListInterface<mixed>        $list
     */
    private function assertSigilNamesAStaticProperty(
        Symbol|PersistentListInterface $segment,
        bool $isStaticPlace,
        PersistentListInterface $list,
    ): void {
        $member = $segment instanceof PersistentListInterface ? $segment->get(0) : $segment;
        if (!$member instanceof Symbol) {
            return;
        }

        $name = $member->getName();
        if (!str_starts_with($name, '$') || $isStaticPlace) {
            return;
        }

        throw AnalyzerException::withLocation(
            sprintf(
                "'%s' names a static property, which only a class can hold: write \\Foo/%s or (php/:: \\Foo %s). "
                . 'An instance member and a method name carry no sigil.',
                $name,
                $name,
                $name,
            ),
            $list,
        );
    }
}
