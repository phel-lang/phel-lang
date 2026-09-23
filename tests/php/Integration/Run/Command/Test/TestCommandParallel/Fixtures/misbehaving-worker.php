<?php

declare(strict_types=1);

// Stand-in for `bin/phel _test-worker` that answers every namespace with a
// pass, except:
// - `app.noisy-test` first writes far more to stderr than a pipe holds, with a
//   deadline, so a parent that never drains stderr gets a failure, not a hang;
// - `app.fatal-test` prints a fatal error to stdout and exits;
// - `app.stray-test` writes to stdout outside a frame and hangs.

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
    $ok = true;

    if ($request['ns'] === 'app.noisy-test') {
        $pending = str_repeat("deprecated: noise\n", 15_000);
        $deadline = microtime(true) + 5.0;
        while ($pending !== '' && microtime(true) < $deadline) {
            $written = fwrite(STDERR, $pending);
            $written > 0 ? $pending = substr($pending, $written) : usleep(1_000);
        }

        $ok = $pending === '';
    }

    if ($request['ns'] === 'app.fatal-test') {
        fwrite(STDOUT, "PHP Fatal error:  Allowed memory size exhausted in /app/fatal.phel\n");
        exit(255);
    }

    if ($request['ns'] === 'app.stray-test') {
        fwrite(STDOUT, "stray output that is not a frame\n");
        sleep(30);
    }

    $body = json_encode([
        'index' => $request['index'],
        'ns' => $request['ns'],
        'ok' => $ok,
        'output' => $ok ? '' : 'stderr stayed full: the parent never drained it',
        'failed-tests' => [],
        'counts' => ['pass' => 1, 'total' => 1],
        'outcome' => 'verdict',
    ]);
    fwrite(STDOUT, str_pad(dechex(\strlen($body)), 8, '0', STR_PAD_LEFT) . "\n" . $body);
}
