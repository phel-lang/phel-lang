<?php

declare(strict_types=1);

use Phel\Config\ProjectConfiguration;
use Phel\Config\TestConfiguration;

return (new ProjectConfiguration())
    ->setTestConfiguration((new TestConfiguration())
        ->setDirectories('Fixtures'));
