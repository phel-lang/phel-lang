<?php

declare(strict_types=1);

// Stand-in for `bin/phel _test-worker`: exits without answering when handed
// `app.dies-test`, prints a fatal error to stdout and exits on `app.fatal-test`,
// writes to stdout outside a frame and hangs on the `$stray` namespaces, and answers
// every other namespace with a pass.

$readExactly = static function (int $length): ?string {
    $data = '';
    while (\strlen($data) < $length) {
        $chunk = fread(STDIN, $length - \strlen($data));
        if ($chunk === false || $chunk === '') {
            return null;
        }

        $data .= $chunk;
    }

    return $data;
};

while (($header = $readExactly(9)) !== null) {
    $request = json_decode((string) $readExactly((int) hexdec(substr($header, 0, 8))), true);
    if ($request['ns'] === 'app.dies-test') {
        exit(3);
    }

    if ($request['ns'] === 'app.fatal-test') {
        fwrite(STDOUT, "PHP Fatal error:  Allowed memory size exhausted in /app/fatal.phel\n");
        exit(255);
    }

    $stray = [
        'app.garbage-test' => "stray output that is not a frame\n",
        'app.stray-byte-test' => 'x',
        'app.stray-hex-test' => "12345678\n",
    ];
    if (isset($stray[$request['ns']])) {
        fwrite(STDOUT, $stray[$request['ns']]);
        sleep(30);
    }

    $body = json_encode([
        'index' => $request['index'],
        'ns' => $request['ns'],
        'ok' => true,
        'output' => '',
        'failed-tests' => [],
        'counts' => ['pass' => 1, 'total' => 1],
        'outcome' => 'verdict',
    ]);
    fwrite(STDOUT, str_pad(dechex(\strlen($body)), 8, '0', STR_PAD_LEFT) . "\n" . $body);
}
