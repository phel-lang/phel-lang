<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\ErrorCode;

use function array_all;
use function count;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_replace;
use function strtolower;
use function substr;

/**
 * @internal
 */
final class PhpIdentifier
{
    private const string UPGRADE_GUIDE = 'https://github.com/phel-lang/phel-lang/blob/main/docs/migration/upgrade-0.49-to-1.0.md#step-3-code-that-no-longer-compiles';

    public static function assertReference(TypeInterface $symbol, string $name): void
    {
        if (in_array($name, PhpVarNode::INFIX_OPERATORS, true) || in_array($name, ['!', '~'], true)) {
            return;
        }

        $classMember = self::classMemberReference($symbol, $name);
        if ($classMember instanceof AnalyzerException) {
            throw $classMember;
        }

        if (str_starts_with($name, '$')) {
            self::assertName($symbol, substr($name, 1));

            return;
        }

        self::assertClassName($symbol, str_replace(['/', '.'], '\\', $name));
    }

    public static function assertName(TypeInterface $symbol, string $name): void
    {
        if (!self::isName($name)) {
            throw AnalyzerException::withLocation(
                'Invalid PHP identifier ' . $name . '. Use letters, digits and underscores, starting with a letter or underscore.',
                $symbol,
                errorCode: ErrorCode::INVALID_SPECIAL_FORM,
            );
        }
    }

    public static function assertClassName(TypeInterface $symbol, string $name): void
    {
        foreach (explode('\\', str_starts_with($name, '\\') ? substr($name, 1) : $name) as $part) {
            self::assertName($symbol, $part);
        }
    }

    private static function classMemberReference(TypeInterface $symbol, string $name): ?AnalyzerException
    {
        $replacement = self::classMemberSpelling($name);
        if ($replacement === null) {
            return null;
        }

        return AnalyzerException::withLocation(
            sprintf(
                'Invalid PHP identifier %s. Write a class member as %s, not php/%s. See %s',
                $name,
                $replacement,
                $name,
                self::UPGRADE_GUIDE,
            ),
            $symbol,
            errorCode: ErrorCode::INVALID_SPECIAL_FORM,
        );
    }

    /**
     * A class whose name starts in lower case reads as a Phel namespace unless
     * it starts with `\`, and after `\` only `\` separates its parts.
     */
    private static function classMemberSpelling(string $name): ?string
    {
        $parts = explode('::', $name, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$class, $member] = $parts;
        $segments = explode('\\', str_replace(['.', '/'], '\\', ltrim($class, '\\')));
        $property = str_starts_with($member, '$') ? substr($member, 1) : $member;
        if (!array_all([...$segments, $property], self::isName(...))
            || in_array(strtolower($segments[0]), ['self', 'static', 'parent'], true)
        ) {
            return null;
        }

        $first = $segments[0][0];
        $className = $first >= 'A' && $first <= 'Z'
            ? implode('.', $segments)
            : '\\' . implode('\\', $segments);

        return $className . '/' . $member;
    }

    private static function isName(string $name): bool
    {
        return preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D', $name) === 1;
    }
}
