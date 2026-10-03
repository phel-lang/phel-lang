<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Shared\Api\PhelFunction;
use Phel\Shared\ScalarCoercion;

use function explode;
use function implode;
use function is_iterable;
use function is_string;
use function trim;

/**
 * The full documentation of one function, as `phel doc <exact-name>` prints it:
 * name, signatures, docstring, deprecation, example and see-also.
 *
 * @internal
 */
final readonly class DocViewFormatter
{
    private const string INDENT = '  ';

    public function format(PhelFunction $fn): string
    {
        $lines = [$fn->namespace . '/' . $fn->name, ...$fn->signatures];

        $description = trim($fn->description);
        if ($description !== '') {
            $lines[] = '';
            $lines[] = $description;
        }

        $deprecated = $fn->meta['deprecated'] ?? null;
        if (is_string($deprecated) && $deprecated !== '') {
            $lines[] = '';
            $lines[] = 'Deprecated: ' . $deprecated;
        }

        $example = trim(ScalarCoercion::toString($fn->meta['example'] ?? null));
        if ($example !== '') {
            $lines[] = '';
            $lines[] = 'Example:';
            foreach (explode("\n", $example) as $line) {
                $lines[] = self::INDENT . $line;
            }
        }

        $seeAlso = $this->seeAlso($fn->meta['see-also'] ?? null);
        if ($seeAlso !== []) {
            $lines[] = '';
            $lines[] = 'See also: ' . implode(', ', $seeAlso);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function seeAlso(mixed $value): array
    {
        if (!is_iterable($value)) {
            return [];
        }

        $names = [];
        foreach ($value as $name) {
            $names[] = ScalarCoercion::toString($name);
        }

        return $names;
    }
}
