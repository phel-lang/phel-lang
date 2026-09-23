<?php

declare(strict_types=1);

// Stand-in for `bin/phel _test-worker`: answers each work frame, but first
// writes far more to stderr than any pipe buffer holds. The writes are
// non-blocking with a deadline, so a parent that never drains stderr gets a
// failed answer instead of a worker hung forever.

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

stream_set_blocking(STDERR, false);

while (($header = $readExactly(9)) !== null) {
    $request = json_decode((string) $readExactly((int) hexdec(substr($header, 0, 8))), true);

    $pending = str_repeat("deprecated: noise\n", 15_000);
    $deadline = microtime(true) + 5.0;
    while ($pending !== '' && microtime(true) < $deadline) {
        $written = fwrite(STDERR, $pending);
        if ($written === false || $written === 0) {
            usleep(1_000);
            continue;
        }

        $pending = substr($pending, $written);
    }

    $body = json_encode([
        'index' => $request['index'],
        'ns' => $request['ns'],
        'ok' => $pending === '',
        'output' => $pending === '' ? '' : 'stderr stayed full: the parent never drained it',
        'failed-tests' => [],
        'counts' => ['pass' => 1, 'total' => 1],
        'outcome' => 'verdict',
    ]);
    fwrite(STDOUT, str_pad(dechex(\strlen($body)), 8, '0', STR_PAD_LEFT) . "\n" . $body);
}
