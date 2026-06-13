<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\PhpInteropContext;

use function ltrim;
use function preg_match;
use function sprintf;
use function strlen;

/**
 * Hover and signature-help text for PHP-interop symbols, backed by the same
 * reflector and context resolver as completion. Both entry points return null
 * when the cursor is not over a resolvable PHP symbol, so callers fall through
 * to the Phel behaviour.
 */
final readonly class PhpInteropDocResolver
{
    public function __construct(
        private PhpInteropContextResolver $contextResolver = new PhpInteropContextResolver(),
        private PhpInteropReflector $reflector = new PhpInteropReflector(),
    ) {}

    /**
     * Markdown hover for the PHP symbol under the cursor (method, static
     * member, global function, or class), or null.
     */
    public function hoverAt(string $source, int $line, int $col): ?string
    {
        // Extend the cursor to the end of the identifier so the resolver sees
        // the whole symbol as the "prefix", then look that exact name up.
        $wordEndCol = CursorText::wordEndColumn($source, $line, $col);
        $context = $this->contextResolver->resolve($source, $line, $wordEndCol);

        return match ($context->kind) {
            PhpInteropContext::KIND_INSTANCE_MEMBER,
            PhpInteropContext::KIND_STATIC_MEMBER => $this->memberHover($context),
            PhpInteropContext::KIND_GLOBAL_FUNCTION => $this->functionHover($context->prefix),
            PhpInteropContext::KIND_CLASS_NAME => $this->classHover($context->prefix),
            default => null,
        };
    }

    /**
     * LSP SignatureHelp payload for the interop call enclosing the cursor, or
     * null. Covers `(php/new \Class ...)`, `(php/-> recv (method ...))`, and
     * `(php/:: Class (method ...))`.
     *
     * @return array{signatures: list<array{label: string}>, activeSignature: int, activeParameter: int}|null
     */
    public function signatureAt(string $source, int $line, int $col): ?array
    {
        $before = CursorText::before($source, $line, $col);

        // (php/new \Class <args>
        if (preg_match('/\(\s*php\/new\s+\\\\?([A-Za-z0-9_\\\\]+)\s/', $before, $m) === 1) {
            $signature = $this->reflector->methodSignature($m[1], '__construct')
                ?? ($m[1] . '()');

            return $this->signatureResponse(sprintf('new %s', $signature));
        }

        // (php/:: Class (method <args>
        if (preg_match('/\(\s*php\/::\s+(.+?)\s+\(([A-Za-z0-9_]+)\s/s', $before, $m) === 1) {
            return $this->callSignature($m[1], $m[2]);
        }

        // (php/-> receiver (method <args>
        if (preg_match('/\(\s*php\/->\s+(.+?)\s+\(([A-Za-z0-9_]+)\s/s', $before, $m) === 1) {
            return $this->callSignature($m[1], $m[2]);
        }

        return null;
    }

    /**
     * @return array{signatures: list<array{label: string}>, activeSignature: int, activeParameter: int}|null
     */
    private function callSignature(string $receiver, string $method): ?array
    {
        $context = $this->contextResolver->resolve(
            sprintf('(php/-> %s x', $receiver),
            1,
            strlen(sprintf('(php/-> %s x', $receiver)) + 1,
        );

        $class = $context->class;
        if ($class === '') {
            return null;
        }

        $signature = $this->reflector->methodSignature($class, $method);

        return $signature === null ? null : $this->signatureResponse($signature);
    }

    private function memberHover(PhpInteropContext $context): ?string
    {
        if ($context->prefix === '') {
            return null;
        }

        $signature = $this->reflector->methodSignature($context->class, $context->prefix);
        if ($signature === null) {
            return null;
        }

        return $this->markdown(sprintf('%s::%s', $context->class, $context->prefix), $signature);
    }

    private function functionHover(string $function): ?string
    {
        $signature = $this->reflector->functionSignature($function);

        return $signature === null ? null : $this->markdown('php/' . $function, $signature);
    }

    private function classHover(string $class): ?string
    {
        $name = ltrim($class, '\\');
        if ($name === '' || $this->reflector->classNames($name) === []) {
            return null;
        }

        return sprintf('**\\%s** _(php class)_', $name);
    }

    private function markdown(string $title, string $signature): string
    {
        return sprintf("**%s** _(php)_\n\n```php\n%s\n```", $title, $signature);
    }

    /**
     * @return array{signatures: list<array{label: string}>, activeSignature: int, activeParameter: int}
     */
    private function signatureResponse(string $label): array
    {
        return [
            'signatures' => [['label' => $label]],
            'activeSignature' => 0,
            'activeParameter' => 0,
        ];
    }

}
