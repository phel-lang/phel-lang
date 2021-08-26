<?php

declare(strict_types=1);

use Phel\ProjectConfiguration;

return (new ProjectConfiguration())
    ->setTestsDirectories('Fixtures')
    ->toArray();
