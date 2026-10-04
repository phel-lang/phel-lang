<?php

declare(strict_types=1);

// Router for `php -S`: /redirect answers with a redirect, every other path
// echoes the request it received as JSON.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/redirect') {
    http_response_code((int) ($_GET['code'] ?? 302));
    header('Location: ' . $_GET['to']);

    return true;
}

if ($path === '/loop') {
    http_response_code(302);
    header('Location: /loop');

    return true;
}

$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[strtolower($name)] = $value;
}

header('Content-Type: application/json');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $path,
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
]);

return true;
