<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Doctor;

/**
 * @internal
 */
enum InstallMode
{
    case Phar;
    case Composer;
    case SourceCheckout;

    public function label(): string
    {
        return match ($this) {
            self::Phar => 'phel.phar',
            self::Composer => 'Composer',
            self::SourceCheckout => 'a source checkout',
        };
    }
}
