<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\RegexParserException;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;

use function sprintf;

/**
 * Parses regex literal tokens (#"pattern") into StringNode values
 * containing PCRE-compatible delimited patterns ("/pattern/").
 *
 * @internal
 */
final class RegexParser
{
    /**
     * @throws RegexParserException
     */
    public function parse(Token $token): StringNode
    {
        $raw = substr($token->getCode(), 2, -1);
        $pattern = str_replace('\\"', '"', $raw);

        // Escape unescaped forward slashes so /delimiter/ is never broken
        $pattern = preg_replace('/(?<!\\\\)\\//', '\\/', $pattern) ?? $pattern;

        $delimited = '/' . $pattern . '/';

        $this->assertCompiles($delimited, $token->getCode());

        return new StringNode(
            $token->getCode(),
            $token->getStartLocation(),
            $token->getEndLocation(),
            $delimited,
        );
    }

    private function assertCompiles(string $delimited, string $literal): void
    {
        $problem = null;
        set_error_handler(static function (int $level, string $message) use (&$problem): bool {
            $problem = $message;

            return true;
        });

        try {
            $result = preg_match($delimited, '');
        } finally {
            restore_error_handler();
        }

        if ($result !== false) {
            return;
        }

        $message = $problem ?? preg_last_error_msg();
        $message = preg_replace('/^preg_match\(\): (Compilation failed: )?/', '', $message) ?? $message;

        throw new RegexParserException(sprintf('Invalid regex %s: %s', $literal, $message));
    }
}
