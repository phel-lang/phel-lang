<?php

require __DIR__ . '/../vendor/autoload.php';

// After `phel build`, serve the compiled app; until then, compile from src/.
$built = __DIR__ . '/../out/index.php';
if (is_file($built)) {
    require $built;
} else {
    \Phel::run(__DIR__ . '/..', 'http-json-api.entry');
}
