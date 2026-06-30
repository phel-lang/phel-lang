<?php

declare(strict_types=1);

namespace Phel\Config;

use Gacela\Framework\Config\ConfigReaderInterface;
use JsonSerializable;
use RuntimeException;

use function file_exists;
use function is_array;
use function pathinfo;

use const PATHINFO_EXTENSION;

/**
 * Like Gacela's PhpConfigReader, except a `phel-config.php` that evaluates to
 * `null` is rejected instead of silently coerced to an empty config. Gacela's
 * own reader maps a null return to `[]`, so a config that returns nothing
 * usable — a forgotten or typo'd `return` — would apply zero settings with no
 * error, the exact silent misconfiguration #2642 reports.
 *
 * Every other shape keeps Gacela's contract: a JsonSerializable (e.g.
 * {@see PhelConfig}) or a plain array is accepted, and any non-array throws the
 * same message PhpConfigReader uses — so {@see ConfigLoadException::wrapIfConfigError()}
 * upgrades it to a file-named, actionable error.
 */
final class StrictPhpConfigReader implements ConfigReaderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function read(string $absolutePath): array
    {
        if (pathinfo($absolutePath, PATHINFO_EXTENSION) !== 'php' || !file_exists($absolutePath)) {
            return [];
        }

        /**
         * @psalm-suppress UnresolvableInclude
         *
         * @var mixed $content
         */
        $content = include $absolutePath;

        if ($content instanceof JsonSerializable) {
            /** @var array<string, mixed> $serialized */
            $serialized = $content->jsonSerialize();

            return $serialized;
        }

        if (is_array($content)) {
            /** @var array<string, mixed> $content */
            return $content;
        }

        throw new RuntimeException('The PHP config file must return an array or a JsonSerializable object!');
    }
}
