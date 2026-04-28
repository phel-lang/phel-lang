<?php

declare(strict_types=1);

namespace Phel\Watch\Transfer;

/**
 * A detected change on a watched path.
 */
final readonly class WatchEvent
{
    public const string KIND_MODIFIED = 'modified';

    public const string KIND_CREATED = 'created';

    public const string KIND_DELETED = 'deleted';

    public function __construct(
        public string $path,
        public string $kind = self::KIND_MODIFIED,
    ) {}
}
