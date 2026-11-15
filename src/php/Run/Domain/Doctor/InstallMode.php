<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Doctor;

use function dirname;
use function is_file;
use function rtrim;
use function str_ends_with;
use function str_replace;

/**
 * @internal
 */
enum InstallMode
{
    case Phar;
    case Composer;
    case SourceCheckout;

    /**
     * Composer puts Phel at `<vendor-dir>/phel-lang/phel-lang` next to
     * `<vendor-dir>/composer/`, whatever the vendor dir is called.
     */
    public static function detect(string $pharPath, string $phelRoot): self
    {
        if ($pharPath !== '') {
            return self::Phar;
        }

        $root = rtrim(str_replace('\\', '/', $phelRoot), '/');

        return str_ends_with($root, '/phel-lang/phel-lang') && is_file(dirname($root, 2) . '/composer/installed.json')
            ? self::Composer
            : self::SourceCheckout;
    }

    public function label(): string
    {
        return match ($this) {
            self::Phar => 'phel.phar',
            self::Composer => 'Composer',
            self::SourceCheckout => 'a source checkout',
        };
    }
}
