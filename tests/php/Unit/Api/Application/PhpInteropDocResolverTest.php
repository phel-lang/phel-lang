<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpInteropDocResolver;
use PhelTest\Unit\Api\Application\Fixtures\HoverEnum;
use PhelTest\Unit\Api\Application\Fixtures\HoverFixture;
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

    public function test_hover_on_method_includes_phpdoc(): void
    {
        $source = '(let [^\\' . HoverFixture::class . " obj (x)]\n  (php/-> obj increment))";

        $hover = $this->resolver->hoverAt($source, 2, 16);

        self::assertNotNull($hover);
        self::assertStringContainsString('increment(', $hover);
        self::assertStringContainsString('Increments the counter', $hover);
    }

    public function test_hover_on_instance_property(): void
    {
        $source = '(let [^\\' . HoverFixture::class . " obj (x)]\n  (php/-> obj count))";

        $hover = $this->resolver->hoverAt($source, 2, 16);

        self::assertNotNull($hover);
        self::assertStringContainsString('int $count', $hover);
        self::assertStringContainsString('The current count', $hover);
    }

    public function test_hover_on_static_constant(): void
    {
        $prefix = '(php/:: \\' . HoverFixture::class . ' ';
        $source = $prefix . 'MAX)';

        $hover = $this->resolver->hoverAt($source, 1, strlen($prefix) + 2);

        self::assertNotNull($hover);
        self::assertStringContainsString('const MAX = 10', $hover);
        self::assertStringContainsString('largest value', $hover);
    }

    public function test_hover_on_enum_case(): void
    {
        $prefix = '(php/:: \\' . HoverEnum::class . ' ';
        $source = $prefix . 'First)';

        $hover = $this->resolver->hoverAt($source, 1, strlen($prefix) + 2);

        self::assertNotNull($hover);
        self::assertStringContainsString('case First = "first"', $hover);
        self::assertStringContainsString('very first case', $hover);
    }

    public function test_hover_on_class_shows_kind_interfaces_and_constructor(): void
    {
        $source = '(php/new \\' . HoverFixture::class . ')';
        $col = (int) strpos($source, 'HoverFixture') + 4;

        $hover = $this->resolver->hoverAt($source, 1, $col);

        self::assertNotNull($hover);
        self::assertStringContainsString('final class', $hover);
        self::assertStringContainsString('implements', $hover);
        self::assertStringContainsString('HoverContract', $hover);
        self::assertStringContainsString('new ', $hover);
        self::assertStringContainsString('string $label', $hover);
        self::assertStringContainsString('counter', $hover);
    }

    public function test_signature_help_populates_parameters(): void
    {
        $source = '(php/-> (php/new \\DateTimeImmutable) (setDate ';
        $help = $this->resolver->signatureAt($source, 1, strlen($source) + 1);

        self::assertNotNull($help);
        $parameters = $help['signatures'][0]['parameters'];
        self::assertCount(3, $parameters, 'setDate(int $year, int $month, int $day)');
        self::assertArrayHasKey('label', $parameters[0]);
    }

    public function test_signature_help_active_parameter_tracks_cursor(): void
    {
        $source = '(php/-> (php/new \\DateTimeImmutable) (setDate 2020 1 ';
        $help = $this->resolver->signatureAt($source, 1, strlen($source) + 1);

        self::assertNotNull($help);
        self::assertStringContainsString('setDate(', $help['signatures'][0]['label']);
        self::assertSame(2, $help['activeParameter']);
    }

    public function test_signature_help_chained_call_targets_innermost_method(): void
    {
        // A lazy regex would latch onto the first `(modify ...)` segment; the
        // structural scan must report the enclosing `setDate` instead.
        $source = '(php/-> (php/new \\DateTimeImmutable) (modify "x") (setDate 2020 ';
        $help = $this->resolver->signatureAt($source, 1, strlen($source) + 1);

        self::assertNotNull($help);
        self::assertStringContainsString('setDate(', $help['signatures'][0]['label']);
        self::assertStringNotContainsString('modify(', $help['signatures'][0]['label']);
        self::assertSame(1, $help['activeParameter']);
    }

    public function test_signature_help_constructor_label_uses_class_name(): void
    {
        $source = '(php/new \\DateTimeImmutable ';
        $help = $this->resolver->signatureAt($source, 1, strlen($source) + 1);

        self::assertNotNull($help);
        self::assertStringStartsWith('new DateTimeImmutable(', $help['signatures'][0]['label']);
    }

    public function test_signature_help_null_for_plain_phel(): void
    {
        self::assertNull($this->resolver->signatureAt('(map inc ', 1, 9));
    }
}
