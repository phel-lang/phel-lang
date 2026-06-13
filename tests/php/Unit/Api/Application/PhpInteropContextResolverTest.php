<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpInteropContextResolver;
use Phel\Api\Transfer\PhpInteropContext;
use PHPUnit\Framework\TestCase;

use function strlen;

final class PhpInteropContextResolverTest extends TestCase
{
    private PhpInteropContextResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PhpInteropContextResolver();
    }

    public function test_instance_member_after_reader_tag_binding(): void
    {
        $source = "(let [^\\DateTimeImmutable dt (make)]\n  (php/-> dt (get";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('get', $context->prefix);
    }

    public function test_instance_member_with_tag_map_binding(): void
    {
        $source = "(let [^{:tag \\DateTimeImmutable} dt (make)]\n  (php/-> dt get";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('get', $context->prefix);
    }

    public function test_instance_member_from_inline_php_new_receiver(): void
    {
        $source = '(php/-> (php/new \\DateTimeImmutable) get';
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
    }

    public function test_instance_member_from_php_new_let_binding(): void
    {
        $source = "(let [dt (php/new \\DateTimeImmutable)]\n  (php/-> dt get";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
    }

    public function test_static_member_after_class_literal(): void
    {
        $source = '(php/:: \\DateTimeImmutable create';
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('create', $context->prefix);
    }

    public function test_class_name_after_php_new(): void
    {
        $context = $this->resolveAtEnd('(php/new \\DateTimeImm');

        self::assertSame(PhpInteropContext::KIND_CLASS_NAME, $context->kind);
        self::assertSame('DateTimeImm', $context->prefix);
    }

    public function test_class_name_for_fully_qualified_position(): void
    {
        $context = $this->resolveAtEnd('(def x \\DateTimeImm');

        self::assertSame(PhpInteropContext::KIND_CLASS_NAME, $context->kind);
        self::assertSame('DateTimeImm', $context->prefix);
    }

    public function test_global_function_after_php_prefix(): void
    {
        $context = $this->resolveAtEnd('(php/strle');

        self::assertSame(PhpInteropContext::KIND_GLOBAL_FUNCTION, $context->kind);
        self::assertSame('strle', $context->prefix);
    }

    public function test_unknown_receiver_type_is_none(): void
    {
        $source = '(php/-> mystery-thing get';
        $context = $this->resolveAtEnd($source);

        self::assertTrue($context->isNone());
    }

    public function test_plain_phel_code_is_none(): void
    {
        $context = $this->resolveAtEnd('(defn foo [x] (inc ');

        self::assertTrue($context->isNone());
    }

    private function resolveAtEnd(string $source): PhpInteropContext
    {
        $lastNewline = strrpos($source, "\n");
        $line = substr_count($source, "\n") + 1;
        $col = ($lastNewline === false ? strlen($source) : strlen($source) - $lastNewline - 1) + 1;

        return $this->resolver->resolve($source, $line, $col);
    }
}
