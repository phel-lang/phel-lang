<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Doctor;

use function ltrim;
use function sprintf;
use function substr;

/**
 * One package from Composer's `installed.json`. Two copies hold the same code
 * when their commit references match; a version label alone misleads, since
 * `dev-main` names different commits and an alias names the same one twice.
 *
 * @internal
 */
final readonly class InstalledPackage
{
    public function __construct(
        public string $version,
        public string $reference = '',
    ) {}

    public function sameCodeAs(self $other): bool
    {
        if ($this->reference !== '' && $other->reference !== '') {
            return $this->reference === $other->reference;
        }

        return $this->label() === $other->label();
    }

    public function label(): string
    {
        return ltrim($this->version, 'v');
    }

    public function labelAgainst(self $other): string
    {
        return $this->label() === $other->label() && $this->reference !== ''
            ? sprintf('%s (%s)', $this->label(), substr($this->reference, 0, 7))
            : $this->label();
    }
}
