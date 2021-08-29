<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;

return static fn () => new class() extends AbstractConfigGacela {
    public function config(): array
    {
        return [
            'type' => 'php',
            'path' => 'phel-config.php',
            'path_local' => 'phel-config-local.php',
        ];
    }
};
