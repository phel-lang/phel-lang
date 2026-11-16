<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\ErrorCode;

use function count;
use function explode;
use function in_array;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * @internal
 */
final class PhpIdentifier
{
    private const string UPGRADE_GUIDE = 'https://github.com/phel-lang/phel-lang/blob/main/docs/migration/upgrade-0.49-to-1.0.md';

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
        if (preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D', $name) !== 1) {
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

    /**
     * `php/PDO::ATTR_ERRMODE` compiled before 1.0; the class member syntax is
     * `PDO/ATTR_ERRMODE`, so name that spelling instead of the allowed characters.
     */
    private static function classMemberReference(TypeInterface $symbol, string $name): ?AnalyzerException
    {
        $parts = explode('::', $name, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$class, $member] = $parts;
        if (ltrim($class, '\\') === '' || $member === '') {
            return null;
        }

        $qualified = !str_starts_with($class, '\\') && (str_contains($class, '\\') || str_contains($class, '.'));
        $replacement = ($qualified ? '\\' : '') . $class . '/' . $member;

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
}
