<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;
use function json_decode;

/**
 * This is a layer on top of Gacela config, to get the values from the composer.json.
 */
abstract class AbstractPhelConfig extends AbstractConfig
{
    private array $composerJson = [];

    /**
     * @override to load the config from the composer.json
     *
     * @param null|mixed $default
     */
    protected function get(string $key, $default = null)
    {
        if (empty($this->composerJson)) {
            $composerJsonPath = Config::getApplicationRootDir() . DIRECTORY_SEPARATOR . 'composer.json';
            $this->composerJson = json_decode(file_get_contents($composerJsonPath), true);
        }

        return $this->composerJson['extra']['phel'][$key] ?? $default;
    }
}
