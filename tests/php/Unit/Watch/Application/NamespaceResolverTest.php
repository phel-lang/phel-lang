<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application;

use Phel\Watch\Application\NamespaceResolver;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class NamespaceResolverTest extends TestCase
{
    private NamespaceResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new NamespaceResolver();
    }

    public function test_it_resolves_ns_form_from_source(): void
    {
        $source = "(ns my-app\\core)\n(defn foo [] 1)";
        self::assertSame('my-app\\core', $this->resolver->resolveFromSource($source));
    }

    public function test_it_resolves_in_ns_form(): void
    {
        $source = "(in-ns 'my-app\\core)";
        self::assertSame('my-app\\core', $this->resolver->resolveFromSource($source));
    }

    public function test_it_normalises_forward_slash_separator_to_backslash(): void
    {
        $source = '(ns my-app/core)';
        self::assertSame('my-app\\core', $this->resolver->resolveFromSource($source));
    }

    public function test_it_skips_shebang_and_leading_line_comments(): void
    {
        $source = "#!/usr/bin/env phel\n;; top comment\n;; another\n(ns app\\main)";
        self::assertSame('app\\main', $this->resolver->resolveFromSource($source));
    }

    public function test_it_returns_null_when_no_ns_form(): void
    {
        self::assertNull($this->resolver->resolveFromSource('(defn foo [] 1)'));
        self::assertNull($this->resolver->resolveFromSource(''));
    }

    public function test_it_resolves_from_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'watch-ns-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, "(ns demo\\core)\n");
            self::assertSame('demo\\core', $this->resolver->resolveFromFile($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_returns_null_for_missing_file(): void
    {
        self::assertNull($this->resolver->resolveFromFile('/non/existent/path.phel'));
    }
}
