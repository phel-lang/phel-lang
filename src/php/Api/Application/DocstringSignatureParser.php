<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function explode;
use function preg_match;
use function trim;

/**
 * Pulls the function signature and description out of a Phel docstring.
 *
 * Docstrings follow the convention:
 *
 * ```phel
 * (my-fn arg1 arg2)
 * (my-fn arg1 arg2 arg3)
 * ```
 * Human-readable prose follows.
 *
 * The parser returns `{signatures, description}`; the surrounding
 * ```phel fence and trailing newline are stripped.
 */
final class DocstringSignatureParser
{
    /**
     * @return array{signatures: list<string>, description: string}
     */
    public static function parse(string $docstring): array
    {
        preg_match('#(```phel\n(?<signature>.*)\n```\n)?(?<desc>.*)#s', $docstring, $matches);

        $signatureBlock = $matches['signature'] ?? '';
        $description = $matches['desc'] ?? '';

        return [
            'signatures' => self::splitSignatures($signatureBlock),
            'description' => $description,
        ];
    }

    /**
     * @return list<string>
     */
    private static function splitSignatures(string $signatureBlock): array
    {
        if ($signatureBlock === '') {
            return [];
        }

        $signatures = [];
        foreach (explode("\n", $signatureBlock) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $signatures[] = $line;
            }
        }

        return $signatures;
    }
}
