<?php

declare(strict_types=1);

// Router for `php -S`: /redirect answers with a redirect, every other path
// echoes the request it received as JSON.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_ends_with((string) $path, '/redirect') && isset($_GET['to'])) {
    http_response_code((int) ($_GET['code'] ?? 302));
    header('Location: ' . $_GET['to']);

    return true;
}

if ($path === '/loop') {
    http_response_code(302);
    header('Location: /loop');

    return true;
}

if ($path === '/bytes') {
    http_response_code((int) ($_GET['status'] ?? 200));
    header('Content-Type: application/octet-stream');
    echo str_repeat('x', (int) ($_GET['size'] ?? 0));

    return true;
}

if ($path === '/pause') {
    header('Content-Length: 2');
    echo 'x';
    flush();
    usleep(200000);
    echo 'y';

    return true;
}

$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[strtolower($name)] = $value;
}

header('Content-Type: application/json');
header('X-Method: ' . $_SERVER['REQUEST_METHOD']);
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $path,
    'query' => $_SERVER['QUERY_STRING'] ?? '',
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
]);

return true;
