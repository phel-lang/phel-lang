<?php

declare(strict_types=1);

namespace Phel;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;
use RuntimeException;

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
            $this->composerJson = $this->readComposerJson();
        }

        return $this->composerJson['extra']['phel'][$key] ?? $default;
    }

    private function readComposerJson(): array
    {
        $composerJsonPath = Config::getApplicationRootDir() . DIRECTORY_SEPARATOR . 'composer.json';
        if (!file_exists($composerJsonPath)) {
            throw new RuntimeException('composer.json not found?');
        }

        $content = json_decode(file_get_contents($composerJsonPath), true);
        if (null === $content) {
            throw new RuntimeException("composer.json malformed and not parsable.\nPath: $composerJsonPath");
        }

        return $content;
    }
}
