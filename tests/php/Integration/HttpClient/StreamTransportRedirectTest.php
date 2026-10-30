<?php

declare(strict_types=1);

namespace PhelTest\Integration\HttpClient;

use InvalidArgumentException;
use Phel\HttpClient\StreamTransport;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function dirname;
use function json_decode;
use function rawurlencode;

final class StreamTransportRedirectTest extends TestCase
{
    private const array SECRET_HEADERS = [
        'x-api-key' => 'secret-key',
        'authorization' => 'Bearer secret-token',
        'accept' => 'application/json',
    ];

    private static LocalHttpServer $origin;

    private static LocalHttpServer $other;

    public static function setUpBeforeClass(): void
    {
        self::$origin = LocalHttpServer::start();
        self::$other = LocalHttpServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$origin->stop();
        self::$other->stop();
    }

    public function test_valid_headers_keep_their_values(): void
    {
        $result = StreamTransport::send('GET', self::$origin->url('/echo'), [
            'x-test' => 'plain value',
            'x-empty' => '',
            'x-number' => 42,
            'x-zero' => 0,
            '123' => 'numbered name',
            "x!#$%&'*+-.^_`|~" => 'token',
        ], null, []);

        $headers = json_decode($result['body'], true)['headers'];
        self::assertSame('plain value', $headers['x-test']);
        self::assertSame('', $headers['x-empty']);
        self::assertSame('42', $headers['x-number']);
        self::assertSame('0', $headers['x-zero']);
        self::assertSame('numbered name', $headers['123']);
        self::assertSame('token', $headers["x!#$%&'*+-.^_`|~"]);
    }

    public function test_a_redirect_to_another_origin_drops_custom_and_credential_headers(): void
    {
        $echo = $this->send('GET', $this->redirectTo(self::$other->url('/echo')));

        self::assertSame('/echo', $echo['path']);
        self::assertArrayNotHasKey('x-api-key', $echo['headers']);
        self::assertArrayNotHasKey('authorization', $echo['headers']);
        self::assertSame('application/json', $echo['headers']['accept']);
    }

    public function test_a_plain_request_returns_status_headers_and_body(): void
    {
        $result = StreamTransport::send('GET', self::$origin->url('/echo'), [], null, []);

        self::assertSame(200, $result['status']);
        self::assertSame('application/json', $result['headers']['content-type']);
        self::assertSame('/echo', json_decode($result['body'], true)['path']);
    }

    public function test_a_redirect_within_the_origin_keeps_every_header(): void
    {
        $echo = $this->send('GET', $this->redirectTo('/echo'));

        self::assertSame('/echo', $echo['path']);
        self::assertSame('secret-key', $echo['headers']['x-api-key']);
        self::assertSame('Bearer secret-token', $echo['headers']['authorization']);
    }

    public function test_a_same_origin_relative_redirect_keeps_url_credentials(): void
    {
        $url = str_replace('http://', 'http://alice:secret@', $this->redirectTo('/echo'));
        $response = StreamTransport::send('GET', $url, [], null, []);
        $echo = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Basic ' . base64_encode('alice:secret'), $echo['headers']['authorization'] ?? null);
    }

    public function test_same_origin_absolute_and_scheme_relative_redirects_keep_url_credentials(): void
    {
        foreach ([self::$origin->url('/echo'), substr(self::$origin->url('/echo'), 5)] as $target) {
            $url = str_replace('http://', 'http://alice:secret@', $this->redirectTo($target));
            $response = StreamTransport::send('GET', $url, [], null, []);
            $echo = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);

            self::assertSame('Basic ' . base64_encode('alice:secret'), $echo['headers']['authorization'] ?? null);
        }
    }

    public function test_percent_encoded_url_credentials_survive_same_origin_redirects(): void
    {
        $credentials = 'alice%40example.com:se%3Acret%25';
        $direct = StreamTransport::send('GET', str_replace('http://', 'http://' . $credentials . '@', self::$origin->url('/echo')), [], null, []);
        $expected = json_decode($direct['body'], true, flags: JSON_THROW_ON_ERROR)['headers']['authorization'];
        $redirect = StreamTransport::send('GET', str_replace('http://', 'http://' . $credentials . '@', $this->redirectTo(self::$origin->url('/echo'))), [], null, []);

        self::assertSame($expected, json_decode($redirect['body'], true, flags: JSON_THROW_ON_ERROR)['headers']['authorization'] ?? null);
    }

    public function test_a_cross_origin_redirect_does_not_inherit_url_credentials(): void
    {
        $url = str_replace('http://', 'http://alice:secret@', $this->redirectTo(self::$other->url('/echo')));
        $response = StreamTransport::send('GET', $url, [], null, []);
        $echo = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('authorization', $echo['headers']);
    }

    public function test_explicit_same_origin_target_credentials_are_not_overwritten(): void
    {
        $target = str_replace('http://', 'http://bob:replacement@', self::$origin->url('/echo'));
        $url = str_replace('http://', 'http://alice:secret@', $this->redirectTo($target));
        $response = StreamTransport::send('GET', $url, [], null, []);

        self::assertSame('Basic ' . base64_encode('bob:replacement'), json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR)['headers']['authorization']);
    }

    public static function permitted_body_limits(): iterable
    {
        yield 'empty and zero' => [0, ['max_body_bytes' => 0]];
        yield 'exact' => [5, ['max_body_bytes' => 5]];
        yield 'under cap' => [4, ['max_body_bytes' => 5]];
        yield 'native maximum' => [5, ['max_body_bytes' => PHP_INT_MAX]];
        yield 'explicit unlimited' => [131072, ['max_body_bytes' => null]];
        yield 'default unlimited' => [131072, []];
    }

    #[DataProvider('permitted_body_limits')]
    public function test_permitted_body_sizes_preserve_the_complete_response(int $size, array $options): void
    {
        $result = StreamTransport::send('GET', self::$origin->url('/bytes?size=' . $size), [], null, $options);

        self::assertSame(str_repeat('x', $size), $result['body']);
        self::assertSame(200, $result['status']);
    }

    public function test_a_zero_body_limit_rejects_a_nonempty_response(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds max_body_bytes limit of 0');

        StreamTransport::send('GET', self::$origin->url('/bytes?size=1'), [], null, ['max_body_bytes' => 0]);
    }

    public function test_a_non_success_status_still_returns_its_body_within_the_limit(): void
    {
        $result = StreamTransport::send('GET', self::$origin->url('/bytes?size=5&status=404'), [], null, ['max_body_bytes' => 5]);

        self::assertSame(404, $result['status']);
        self::assertSame('xxxxx', $result['body']);
    }

    public function test_a_response_past_the_body_limit_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds max_body_bytes limit of 32');

        StreamTransport::send('GET', self::$origin->url('/bytes?size=131072'), [], null, ['max_body_bytes' => 32]);
    }

    public function test_oversize_reads_only_one_byte_past_the_limit_and_closes_the_stream(): void
    {
        $result = $this->readProbe('oversize');

        self::assertSame(33, $result['bytes_read']);
        self::assertSame(1, $result['closed']);
        self::assertStringContainsString('exceeds max_body_bytes limit of 32', (string) $result['error']);
    }

    public function test_a_body_read_failure_throws_and_closes_the_stream(): void
    {
        $result = $this->readProbe('failure');

        self::assertSame(0, $result['bytes_read']);
        self::assertSame(1, $result['closed']);
        self::assertStringContainsString('Unable to read response body', (string) $result['error']);
    }

    public function test_a_successful_body_read_also_closes_the_stream(): void
    {
        $result = $this->readProbe('success');

        self::assertSame(5, $result['bytes_read']);
        self::assertSame(1, $result['closed']);
        self::assertSame('', $result['error']);
    }

    public function test_a_response_body_timeout_is_a_failure_instead_of_a_partial_body(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Timed out reading response body');

        StreamTransport::send('GET', self::$origin->url('/pause'), [], null, ['timeout' => 0.02]);
    }

    public function test_the_public_phel_request_forwards_body_limits_including_zero(): void
    {
        $large = json_encode(self::$origin->url('/bytes?size=131072'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $exact = json_encode(self::$origin->url('/bytes?size=5'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $empty = json_encode(self::$origin->url('/bytes?size=0'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $one = json_encode(self::$origin->url('/bytes?size=1'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $source = '(ns http-cap-test (:require phel.http-client :as hc))' . "\n"
            . '(println (try (hc/get ' . $large . ' {:max-body-bytes 32}) false (catch RuntimeException error (php/str_contains (.getMessage error) "exceeds max_body_bytes"))))' . "\n"
            . '(println (= "xxxxx" (:body (hc/get ' . $exact . ' {:max-body-bytes 5}))))' . "\n"
            . '(println (= "" (:body (hc/get ' . $empty . ' {:max-body-bytes 0}))))' . "\n"
            . '(println (try (hc/get ' . $one . ' {:max-body-bytes 0}) false (catch RuntimeException error true)))' . "\n"
            . '(println (= 131072 (count (:body (hc/get ' . $large . ' {:max-body-bytes nil})))))' . "\n"
            . '(println (= 131072 (count (:body (hc/get ' . $large . ')))))';
        $cache = realpath(sys_get_temp_dir()) . '/phel-http-cap-' . bin2hex(random_bytes(6));
        $environment = getenv();
        $environment['PHEL_CACHE_DIR'] = $cache;
        $environment['PHEL_NO_OPCACHE_REEXEC'] = '1';

        try {
            $process = Subprocess::run([PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', 'eval', $source], env: $environment);
            self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
            self::assertSame('', $process->stderr);
            self::assertSame(str_repeat("true\n", 6) . "nil\n", $process->stdout);
        } finally {
            if (is_dir($cache)) {
                fwrite(STDERR, 'Removing isolated HTTP cache: ' . $cache . "\n");
                removeDirectory($cache);
            }
        }
    }

    public function test_a_query_only_location_keeps_the_current_path(): void
    {
        $echo = $this->send('GET', self::$origin->url('/items/redirect?to=' . rawurlencode('?page=2')));

        self::assertSame('/items/redirect', $echo['path']);
        self::assertSame('page=2', $echo['query']);
    }

    public function test_a_relative_location_resolves_against_the_current_directory(): void
    {
        $echo = $this->send('GET', self::$origin->url('/a/b/redirect?to=' . rawurlencode('../echo?x=1')));

        self::assertSame('/a/echo', $echo['path']);
        self::assertSame('x=1', $echo['query']);
    }

    public function test_a_303_turns_a_post_into_a_get_without_its_body(): void
    {
        $echo = $this->send('POST', $this->redirectTo('/echo', 303), '{"a":1}');

        self::assertSame('GET', $echo['method']);
        self::assertSame('', $echo['body']);
    }

    public function test_a_redirect_that_drops_the_body_drops_its_headers(): void
    {
        $result = StreamTransport::send(
            'POST',
            $this->redirectTo('/echo', 303),
            ['content-type' => 'application/json', 'content-length' => '7'],
            '{"a":1}',
            ['timeout' => 5],
        );

        /** @var array{headers: array<string, string>} $echo */
        $echo = json_decode($result['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('content-length', $echo['headers']);
        self::assertArrayNotHasKey('content-type', $echo['headers']);
    }

    public function test_a_303_keeps_a_head_request_a_head(): void
    {
        $result = StreamTransport::send('HEAD', $this->redirectTo('/echo', 303), [], null, ['timeout' => 5]);

        self::assertSame('HEAD', $result['headers']['x-method']);
    }

    public function test_a_307_keeps_the_method_and_the_body(): void
    {
        $echo = $this->send('POST', $this->redirectTo('/echo', 307), '{"a":1}');

        self::assertSame('POST', $echo['method']);
        self::assertSame('{"a":1}', $echo['body']);
    }

    public function test_a_redirect_is_returned_as_is_when_redirects_are_off(): void
    {
        $result = StreamTransport::send('GET', $this->redirectTo('/echo'), [], null, ['follow_redirects' => false]);

        self::assertSame(302, $result['status']);
        self::assertSame('/echo', $result['headers']['location']);
    }

    public function test_a_redirect_loop_stops(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many redirects');

        StreamTransport::send('GET', self::$origin->url('/loop'), [], null, []);
    }

    public function test_a_redirect_to_a_non_http_scheme_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only http and https URLs');

        StreamTransport::send('GET', $this->redirectTo('file:///etc/hosts'), [], null, []);
    }

    private function readProbe(string $mode): array
    {
        $process = Subprocess::run([PHP_BINARY, '-r', <<<'PHP'
        namespace Phel\HttpClient {
            function fread($stream, int $length): string|false {
                if ($GLOBALS['mode'] === 'failure') {
                    return false;
                }
                $chunk = \fread($stream, $length);
                $GLOBALS['bytes_read'] += $chunk === false ? 0 : strlen($chunk);
                return $chunk;
            }
            function fclose($stream): bool {
                ++$GLOBALS['closed'];
                return \fclose($stream);
            }
        }
        namespace {
            require $argv[1];
            $GLOBALS['mode'] = $argv[3];
            $GLOBALS['bytes_read'] = 0;
            $GLOBALS['closed'] = 0;
            $error = '';
            try {
                \Phel\HttpClient\StreamTransport::send('GET', $argv[2], [], null, ['max_body_bytes' => 32]);
            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
            echo json_encode(['bytes_read' => $GLOBALS['bytes_read'], 'closed' => $GLOBALS['closed'], 'error' => $error]);
        }
        PHP, dirname(__DIR__, 4) . '/vendor/autoload.php', self::$origin->url('/bytes?size=' . ($mode === 'success' ? 5 : 131072)), $mode]);

        self::assertSame(0, $process->exitCode, $process->stderr);

        return json_decode($process->stdout, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{method: string, path: string, query: string, headers: array<string, string>, body: string}
     */
    private function send(string $method, string $url, ?string $body = null): array
    {
        $result = StreamTransport::send($method, $url, self::SECRET_HEADERS, $body, []);

        /** @var array{method: string, path: string, query: string, headers: array<string, string>, body: string} */
        return json_decode($result['body'], true, flags: JSON_THROW_ON_ERROR);
    }

    private function redirectTo(string $target, int $code = 302): string
    {
        return self::$origin->url('/redirect?code=' . $code . '&to=' . rawurlencode($target));
    }
}
