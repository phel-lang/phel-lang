<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function base64_decode;
use function base64_encode;
use function intdiv;
use function rtrim;
use function str_pad;
use function strlen;
use function strtr;

/**
 * The URL-safe pair of `phel.base64` (#3021 B1). Both used to reach one
 * character swap through a chain of `phel.string/replace` calls, each of which
 * runs `assert-string`, builds a delimited pattern with `preg_quote` and then
 * calls `preg_replace`: 6.98μs for `encode-url` and 7.38μs for `decode-url`
 * against a ~0.13μs empty-closure floor.
 *
 * The pair brackets what the rewrite removes. `bench_encode_url` invokes the
 * Phel function; `bench_encode_url_raw` is the PHP the body now reduces to. The
 * ratio is what to review: it is near 1 while the body stays a `strtr`, and it
 * is what blows up if anyone routes these back through `phel.string/replace`.
 *
 * The sample deliberately contains bytes that encode to `+` and `/` and a
 * length that needs padding, so neither subject measures a no-op swap.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class StdlibBase64Bench extends CoreBenchCase
{
    /** @var callable */
    private $encodeUrl;

    /** @var callable */
    private $decodeUrl;

    private string $plain = '';

    private string $urlEncoded = '';

    /**
     * @Revs(1000)
     */
    public function bench_encode_url(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->encodeUrl)($this->plain);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_encode_url_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            rtrim(strtr(base64_encode($this->plain), '+/', '-_'), '=');
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_decode_url(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->decodeUrl)($this->urlEncoded);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_decode_url_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $swapped = strtr($this->urlEncoded, '-_', '+/');
            base64_decode(str_pad($swapped, 4 * intdiv(strlen($swapped) + 3, 4), '='), false);
        }
    }

    protected function extraNamespaces(): array
    {
        return ['phel.base64'];
    }

    protected function setUpFixtures(): void
    {
        $this->encodeUrl = $this->phelFn('phel.base64', 'encode-url');
        $this->decodeUrl = $this->phelFn('phel.base64', 'decode-url');

        // 0xFA..0xFD encode to `-vv8_Q` once swapped, so the input exercises
        // both replaced characters and the padding branch.
        $this->plain = 'Hello, World!' . "\xfa\xfb\xfc\xfd";
        $this->urlEncoded = rtrim(strtr(base64_encode($this->plain), '+/', '-_'), '=');
    }
}
