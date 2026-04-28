<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Api\Infrastructure\Daemon\ApiDaemon;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function fwrite;
use function json_decode;
use function json_encode;
use function rewind;
use function stream_get_contents;

final class ApiDaemonTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_dispatches_two_json_rpc_requests_over_in_memory_streams(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new ApiFacade();

        $in = fopen('php://temp', 'r+');
        $out = fopen('php://temp', 'r+');
        self::assertNotFalse($in);
        self::assertNotFalse($out);

        // 1. analyzeSource with a broken source -> expect non-empty error diagnostics
        fwrite($in, json_encode([
            'id' => 1,
            'method' => 'analyzeSource',
            'params' => [
                'source' => '(ns user) (unclosed',
                'uri' => 'user.phel',
            ],
        ]) . "\n");

        // 2. indexProject on the fixtures dir -> expect a definitions map
        fwrite($in, json_encode([
            'id' => 2,
            'method' => 'indexProject',
            'params' => [
                'srcDirs' => [__DIR__ . '/Fixtures'],
            ],
        ]) . "\n");

        rewind($in);

        $daemon = new ApiDaemon($facade, $in, $out);
        $daemon->run(2);

        rewind($out);
        $contents = stream_get_contents($out);
        self::assertIsString($contents);

        $lines = array_values(array_filter(explode("\n", $contents), static fn($l): bool => $l !== ''));
        self::assertCount(2, $lines);

        $first = json_decode($lines[0], true);
        self::assertIsArray($first);
        self::assertSame(1, $first['id']);
        self::assertArrayHasKey('result', $first);
        self::assertNotEmpty($first['result']);

        $second = json_decode($lines[1], true);
        self::assertIsArray($second);
        self::assertSame(2, $second['id']);
        self::assertArrayHasKey('result', $second);
        self::assertGreaterThanOrEqual(1, $second['result']['definitions']);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_an_error_response_for_unknown_method(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $facade = new ApiFacade();

        $in = fopen('php://temp', 'r+');
        $out = fopen('php://temp', 'r+');
        self::assertNotFalse($in);
        self::assertNotFalse($out);

        fwrite($in, json_encode([
            'id' => 99,
            'method' => 'nope',
            'params' => [],
        ]) . "\n");
        rewind($in);

        $daemon = new ApiDaemon($facade, $in, $out);
        $daemon->run(1);

        rewind($out);
        $contents = stream_get_contents($out);
        self::assertIsString($contents);
        $decoded = json_decode(trim($contents), true);
        self::assertIsArray($decoded);
        self::assertSame(99, $decoded['id']);
        self::assertArrayHasKey('error', $decoded);
        self::assertSame(-32601, $decoded['error']['code']);
    }
}
