<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Infrastructure;

use Phel\Api\Infrastructure\NativeSymbolCatalog;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_unique;
use function dirname;
use function file_get_contents;
use function in_array;
use function preg_match_all;
use function sprintf;
use function strpos;
use function substr;

final class NativeSymbolCatalogTest extends TestCase
{
    /**
     * Special forms that are intentionally absent from the API reference:
     * each is an internal `*` form or an alias whose user-facing counterpart
     * (`defenum`, `reify`, `php/new`) is documented instead.
     *
     * @var list<string>
     */
    private const array INTERNAL_SPECIAL_FORMS = [
        'new',
        'defenum*',
        'reify*',
    ];

    public function test_definitions_is_non_empty(): void
    {
        self::assertNotSame([], NativeSymbolCatalog::definitions());
    }

    public function test_definitions_include_special_forms_and_builtins(): void
    {
        $definitions = NativeSymbolCatalog::definitions();

        foreach ([Symbol::NAME_IF, Symbol::NAME_FN, Symbol::NAME_DEF, '*file*', '*ns*'] as $symbol) {
            self::assertArrayHasKey($symbol, $definitions);
        }
    }

    public function test_every_entry_exposes_signatures(): void
    {
        foreach (NativeSymbolCatalog::definitions() as $symbol => $meta) {
            self::assertArrayHasKey('signatures', $meta, sprintf('Entry "%s" should declare signatures', $symbol));
            self::assertNotSame([], $meta['signatures']);
        }
    }

    /**
     * Guards against shipping a special form that `phel doc` and the API
     * reference cannot see (the gap that left `load` undocumented). Every
     * special form registered in the analyzer must have a catalog entry or
     * be listed in {@see self::INTERNAL_SPECIAL_FORMS}.
     */
    public function test_every_registered_special_form_is_documented(): void
    {
        $documented = NativeSymbolCatalog::definitions();

        foreach ($this->registeredSpecialFormNames() as $name) {
            if (in_array($name, self::INTERNAL_SPECIAL_FORMS, true)) {
                continue;
            }

            self::assertArrayHasKey(
                $name,
                $documented,
                sprintf(
                    'Special form "%s" is registered in the analyzer but missing from NativeSymbolCatalog. '
                    . 'Add a catalog entry (so it shows in `phel doc`) or list it in INTERNAL_SPECIAL_FORMS.',
                    $name,
                ),
            );
        }
    }

    /**
     * Reads the special-form symbol names from the analyzer's central
     * `match ($symbolName)` registration, resolving each `Symbol::NAME_*`
     * constant to its string value.
     *
     * @return list<string>
     */
    private function registeredSpecialFormNames(): array
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 5)
            . '/src/php/Compiler/Domain/Analyzer/TypeAnalyzer/AnalyzePersistentList.php',
        );

        $start = strpos($source, 'match ($symbolName)');
        $end = strpos($source, 'default =>', $start === false ? 0 : $start);
        self::assertIsInt($start, 'Could not locate the special-form match block');
        self::assertIsInt($end, 'Could not locate the end of the special-form match block');

        $block = substr($source, $start, $end - $start);
        preg_match_all('/Symbol::(NAME_[A-Z_]+)/', $block, $matches);

        $reflection = new ReflectionClass(Symbol::class);
        $names = [];
        foreach (array_unique($matches[1]) as $constant) {
            /** @var string $value */
            $value = $reflection->getConstant($constant);
            $names[] = $value;
        }

        return $names;
    }
}
