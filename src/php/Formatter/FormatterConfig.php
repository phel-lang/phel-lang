<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\ScalarCoercion;

use function array_map;
use function array_values;
use function is_array;

final class FormatterConfig extends AbstractConfig
{
    private const array DEFAULT_FORMAT_DIRS = ['src', 'tests'];

    /**
     * @return list<string>
     */
    public function getFormatDirs(): array
    {
        $formatDirs = $this->get(PhelConfig::FORMAT_DIRS, self::DEFAULT_FORMAT_DIRS);
        if (!is_array($formatDirs)) {
            return self::DEFAULT_FORMAT_DIRS;
        }

        return array_values(array_map(
            static fn(mixed $dir): string => ScalarCoercion::toString($dir),
            $formatDirs,
        ));
    }
}
