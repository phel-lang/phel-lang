<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

final readonly class PhelFnNormalizer implements PhelFnNormalizerInterface
{
    private const PRIVATE_SYMBOLS = [
        Symbol::NAME_APPLY => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_CONCAT => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DEF => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DEF_STRUCT => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DO => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_FN => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_FOREACH => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_IF => [
            'doc' => '```phel
(if test then else?)
```
A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.

The test evaluates to false if its value is false or equal to nil. Every other value evaluates to true. In sense of PHP this means (test != null && test !== false).',
            'fnSignature' => '(if test then else?)',
            'desc' => 'A control flow structure. First evaluates test. If test evaluates to true, only the then form is evaluated and the result is returned. If test evaluates to false only the else form is evaluated and the result is returned. If no else form is given, nil will be returned.',
        ],
        Symbol::NAME_LET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_LOOP => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_NS => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_GET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_PUSH => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_SET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_ARRAY_UNSET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_NEW => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_OBJECT_CALL => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_OBJECT_STATIC_CALL => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_QUOTE => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_RECUR => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_UNQUOTE => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_UNQUOTE_SPLICING => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_THROW => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_TRY => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_PHP_OBJECT_SET => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_LIST => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_VECTOR => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_MAP => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_SET_VAR => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
        Symbol::NAME_DEF_INTERFACE => [
            'doc' => '',
            'fnSignature' => '',
            'desc' => '',
        ],
    ];

    /**
     * @param  list<string>  $allNamespaces
     */
    public function __construct(
        private PhelFnLoaderInterface $phelFnLoader,
        private array $allNamespaces = [],
    ) {
    }

    /**
     * @param  list<string>  $namespaces
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array
    {
        if ($namespaces === []) {
            $namespaces = $this->allNamespaces;
        }

        $normalizedData = $this->phelFnLoader->getNormalizedPhelFunctions($namespaces);

        $result = [];
        foreach ($normalizedData as $fnName => $meta) {
            $isPrivate = $meta[Keyword::create('private')] ?? false;
            if ($isPrivate) {
                continue;
            }

            $doc = $meta[Keyword::create('doc')] ?? '';
            $pattern = '#(```phel\n(?<fnSignature>.*)\n```\n)?(?<desc>.*)#s';
            preg_match($pattern, (string) $doc, $matches);
            $groupKey = $this->groupKey($fnName);

            $result[$groupKey][] = new PhelFunction(
                $fnName,
                $doc,
                $matches['fnSignature'] ?? '',
                $matches['desc'] ?? '',
                $groupKey,
            );
        }

        foreach ($result as $values) {
            usort($values, $this->sortingPhelFunctionsCallback());
        }

        $finalValues = array_merge(...array_values($result));
        $finalValues = $this->addNativeSymbols($finalValues);
        usort($finalValues, $this->sortingPhelFunctionsCallback());

        return $finalValues;
    }

    private function groupKey(string $fnName): string
    {
        $key = preg_replace(
            '/[^a-zA-Z0-9\-]+/',
            '',
            str_replace('/', '-', $fnName),
        );

        return strtolower(rtrim((string) $key, '-'));
    }

    private function sortingPhelFunctionsCallback(): callable
    {
        return static fn (PhelFunction $a, PhelFunction $b): int => $a->fnName() <=> $b->fnName();
    }

    /**
     * @param  list<PhelFunction>  $list
     *
     * @return  list<PhelFunction>
     */
    private function addNativeSymbols(array $list): array
    {
        foreach (self::PRIVATE_SYMBOLS as $symbolName => $meta) {
            $list[] = new PhelFunction(
                $symbolName,
                $meta['doc'] ?? '',
                $meta['fnSignature'] ?? '',
                $meta['desc'] ?? '',
                $this->groupKey($symbolName),
            );

        }

        return $list;
    }
}
