<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use function strlen;
use function strpos;
use function substr;

/**
 * Extracts the text of the first top-level form from Phel source code
 * by matching balanced parentheses, skipping strings and comments.
 */
final readonly class FirstFormExtractor
{
    public function extract(string $code): string
    {
        $len = strlen($code);
        $depth = 0;
        $inString = false;

        for ($i = 0; $i < $len; ++$i) {
            $c = $code[$i];

            if ($inString) {
                if ($c === '\\') {
                    ++$i;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '#') {
                $newline = strpos($code, "\n", $i);
                $i = $newline === false ? $len : $newline;

                continue;
            }

            if ($c === '"') {
                $inString = true;

                continue;
            }

            if ($c === '(') {
                ++$depth;
            } elseif ($c === ')') {
                --$depth;
                if ($depth === 0) {
                    return substr($code, 0, $i + 1);
                }
            }
        }

        return $code;
    }
}
