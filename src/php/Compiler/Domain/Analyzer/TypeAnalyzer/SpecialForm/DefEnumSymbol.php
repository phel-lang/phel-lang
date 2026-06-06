<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefEnumCase;
use Phel\Compiler\Domain\Analyzer\Ast\DefEnumNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function array_slice;
use function count;
use function is_int;
use function is_string;

/**
 * (defenum* Name :case-a value-a :case-b value-b ... <implementations>).
 *
 * Defines a native PHP enum. Each case is named by a keyword; an optional
 * scalar value (all `int` or all `string`) makes it a backed enum, while
 * value-less cases produce a pure enum. After the cases, an optional
 * implementations tail (interface symbols with their methods, and `:php`
 * blocks of bare methods) is parsed like `defstruct`, so an enum can carry
 * methods and implement interfaces.
 */
final readonly class DefEnumSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private InterfaceImplementationsAnalyzer $implementationsAnalyzer,
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefEnumNode
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation("At least one argument is required for 'defenum", $list);
        }

        $name = $list->get(1);
        if (!$name instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType("First argument of 'defenum", 'Symbol', $name, $list);
        }

        $elements = [];
        foreach ($list as $element) {
            $elements[] = $element;
        }

        // Drop the leading `defenum*` symbol and the enum name.
        $elements = array_slice($elements, 2);

        [$cases, $implementationForms] = $this->splitCasesFromImplementations($elements, $list);
        if ($cases === []) {
            throw AnalyzerException::withLocation("'defenum requires at least one case", $list);
        }

        return new DefEnumNode(
            $env,
            $this->analyzer->getNamespace(),
            $name,
            $cases,
            $this->backingType($cases, $list),
            $this->implementationsAnalyzer->analyze(
                Phel::list($implementationForms),
                $env,
                'defenum',
            ),
            $list->getStartLocation(),
        );
    }

    /**
     * Cases come first as leading keyword (with optional scalar value) pairs.
     * Parsing stops at the first non-case form: an interface symbol or the
     * `:php` marker, which begins the implementations tail.
     *
     * @param list<mixed>                    $elements
     * @param PersistentListInterface<mixed> $list
     *
     * @return array{0: list<DefEnumCase>, 1: list<mixed>}
     */
    private function splitCasesFromImplementations(array $elements, PersistentListInterface $list): array
    {
        $count = count($elements);
        $cases = [];
        $index = 0;
        while ($index < $count) {
            $caseKeyword = $elements[$index];
            if (!$caseKeyword instanceof Keyword || $this->implementationsAnalyzer->isPhpMarker($caseKeyword)) {
                break;
            }

            $value = null;
            $next = $elements[$index + 1] ?? null;
            if ($next !== null && !$next instanceof Keyword && !$next instanceof Symbol) {
                if (!is_int($next) && !is_string($next)) {
                    throw AnalyzerException::withLocation('Enum case values must be int or string', $list);
                }

                $value = $next;
                ++$index;
            }

            $cases[] = new DefEnumCase($caseKeyword->getName(), $value);
            ++$index;
        }

        return [$cases, array_slice($elements, $index)];
    }

    /**
     * @param list<DefEnumCase>              $cases
     * @param PersistentListInterface<mixed> $list
     */
    private function backingType(array $cases, PersistentListInterface $list): ?string
    {
        $hasValue = false;
        $hasNull = false;
        $type = null;
        foreach ($cases as $case) {
            $value = $case->getValue();
            if ($value === null) {
                $hasNull = true;
                continue;
            }

            $hasValue = true;
            $currentType = is_int($value) ? 'int' : 'string';
            if ($type === null) {
                $type = $currentType;
            } elseif ($type !== $currentType) {
                throw AnalyzerException::withLocation('Enum case values must be all int or all string', $list);
            }
        }

        if ($hasValue && $hasNull) {
            throw AnalyzerException::withLocation('Enum cases must either all have a value or none', $list);
        }

        return $type;
    }
}
