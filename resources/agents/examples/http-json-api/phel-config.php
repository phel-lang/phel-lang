<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

// `phel build` writes out/index.php for the namespace that serves requests.
return new PhelConfig()
    ->withMainPhelNamespace('http-json-api.entry');
