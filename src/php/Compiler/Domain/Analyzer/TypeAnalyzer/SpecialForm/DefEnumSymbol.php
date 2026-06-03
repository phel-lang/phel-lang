<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\DefEnumCase;
use Phel\Compiler\Domain\Analyzer\Ast\DefEnumNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function array_slice;
use function count;
use function is_int;
use function is_string;

/**
 * (defenum* Name :case-a value-a :case-b value-b ...).
 *
 * Defines a native PHP backed enum. Each case is named by a keyword; an
 * optional scalar value (all `int` or all `string`) makes it a backed enum,
 * while value-less cases produce a pure enum.
 */
final class DefEnumSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

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

        $cases = $this->cases($list);
        if ($cases === []) {
            throw AnalyzerException::withLocation("'defenum requires at least one case", $list);
        }

        return new DefEnumNode(
            $env,
            $this->analyzer->getNamespace(),
            $name,
            $cases,
            $this->backingType($cases, $list),
            $list->getStartLocation(),
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return list<DefEnumCase>
     */
    private function cases(PersistentListInterface $list): array
    {
        $elements = [];
        foreach ($list as $element) {
            $elements[] = $element;
        }

        // Drop the leading `defenum*` symbol and the enum name.
        $elements = array_slice($elements, 2);
        $count = count($elements);

        $cases = [];
        $index = 0;
        while ($index < $count) {
            $caseKeyword = $elements[$index];
            if (!$caseKeyword instanceof Keyword) {
                throw AnalyzerException::withLocation('Each enum case must be a keyword', $list);
            }

            $value = null;
            $next = $elements[$index + 1] ?? null;
            if ($next !== null && !$next instanceof Keyword) {
                if (!is_int($next) && !is_string($next)) {
                    throw AnalyzerException::withLocation('Enum case values must be int or string', $list);
                }

                $value = $next;
                ++$index;
            }

            $cases[] = new DefEnumCase($caseKeyword->getName(), $value);
            ++$index;
        }

        return $cases;
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
