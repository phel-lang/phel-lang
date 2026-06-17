<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpInteropDocResolver;
use PHPUnit\Framework\TestCase;

use function strlen;
use function strpos;

final class PhpInteropDocResolverTest extends TestCase
{
    private PhpInteropDocResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PhpInteropDocResolver();
    }

    public function test_hover_on_instance_method(): void
    {
        $source = '(php/-> (php/new \\DateTimeImmutable) getTimestamp)';
        $cursor = (int) strpos($source, 'getTimestamp') + 3;

        $hover = $this->resolver->hoverAt($source, 1, $cursor);

        self::assertNotNull($hover);
        self::assertStringContainsString('getTimestamp', $hover);
        self::assertStringContainsString('```php', $hover);
    }

    public function test_hover_on_global_function(): void
    {
        $source = '(php/strlen x)';
        $cursor = (int) strpos($source, 'strlen') + 3;

        $hover = $this->resolver->hoverAt($source, 1, $cursor);

        self::assertNotNull($hover);
        self::assertStringContainsString('php/strlen', $hover);
        self::assertStringContainsString('strlen(', $hover);
    }

    public function test_hover_returns_null_for_plain_phel(): void
    {
        self::assertNull($this->resolver->hoverAt('(defn foo [x] x)', 1, 8));
    }

    public function test_signature_help_for_php_new(): void
    {
        $source = '(php/new \\DateTimeImmutable ';
        $help = $this->resolver->signatureAt($source, 1, strlen($source) + 1);

        self::assertNotNull($help);
        self::assertStringContainsString('new', $help['signatures'][0]['label']);
        self::assertSame(0, $help['activeSignature']);
    }

    public function test_signature_help_for_method_call(): void
    {
        $source = '(php/-> (php/new \\DateTimeImmutable) (setTimestamp ';
        $help = $this->resolver->signatureAt($source, 1, strlen($source) + 1);

        self::assertNotNull($help);
        self::assertStringContainsString('setTimestamp(', $help['signatures'][0]['label']);
    }

    public function test_signature_help_for_multiline_method_call(): void
    {
        $source = "(php/-> (php/new \\DateTimeImmutable)\n  (setTimestamp ";
        $help = $this->resolver->signatureAt($source, 2, strlen('  (setTimestamp ') + 1);

        self::assertNotNull($help);
        self::assertStringContainsString('setTimestamp(', $help['signatures'][0]['label']);
    }

    public function test_hover_on_multiline_instance_method(): void
    {
        // Line 2 is "  getTimestamp"; cursor sits inside the method name.
        $source = "(php/-> (php/new \\DateTimeImmutable)\n  getTimestamp";

        $hover = $this->resolver->hoverAt($source, 2, 6);

        self::assertNotNull($hover);
        self::assertStringContainsString('getTimestamp', $hover);
    }

    public function test_signature_help_null_for_plain_phel(): void
    {
        self::assertNull($this->resolver->signatureAt('(map inc ', 1, 9));
    }
}
