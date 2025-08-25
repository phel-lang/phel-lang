<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;

final class RunConfig extends AbstractConfig
{
    public function getPhelReplHistory(): string
    {
        return $this->getAppRootDir() . '.phel-repl-history';
    }

    public function getReplStartupFile(): string
    {
        return __DIR__ . '/Domain/Repl/startup.phel';
    }

    /**
     * @return list<string>
     */
    public function getImportPaths(): array
    {
        /** @var list<string> $paths */
        $paths = $this->get(PhelConfig::IMPORT_PATHS, []);

        return $paths;
    }
}
