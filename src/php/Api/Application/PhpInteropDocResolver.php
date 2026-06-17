<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Transfer\PhpInteropCall;
use Phel\Api\Transfer\PhpInteropClass;
use Phel\Api\Transfer\PhpInteropContext;
use Phel\Api\Transfer\PhpInteropSignature;

use function count;
use function implode;
use function ltrim;
use function min;
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
        private PhpInteropCallScanner $callScanner = new PhpInteropCallScanner(),
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
     * `(php/:: Class (method ...))`. The enclosing call is found structurally so
     * chained calls report the innermost method, and `activeParameter` tracks
     * the argument the cursor sits on.
     *
     * @return array{signatures: list<array{label: string, parameters: list<array{label: string}>, documentation?: string}>, activeSignature: int, activeParameter: int}|null
     */
    public function signatureAt(string $source, int $line, int $col): ?array
    {
        $call = $this->callScanner->scan(CursorText::before($source, $line, $col));

        return match ($call->kind) {
            PhpInteropCall::KIND_CONSTRUCTOR => $this->constructorSignature($call),
            PhpInteropCall::KIND_METHOD => $this->methodCallSignature($call),
            default => null,
        };
    }

    /**
     * @return array{signatures: list<array{label: string, parameters: list<array{label: string}>, documentation?: string}>, activeSignature: int, activeParameter: int}|null
     */
    private function constructorSignature(PhpInteropCall $call): ?array
    {
        $class = ltrim($call->receiver, '\\');
        if (!$this->reflector->classExists($class)) {
            return null;
        }

        // A class without an explicit constructor still gets a `new Class()` hint.
        $info = $this->reflector->methodSignatureInfo($class, '__construct');
        $parameters = $info instanceof PhpInteropSignature ? $info->parameters : [];
        $documentation = $info instanceof PhpInteropSignature ? $info->documentation : '';

        return $this->signatureResponse(
            new PhpInteropSignature(
                sprintf('new %s(%s)', $class, implode(', ', $parameters)),
                $parameters,
                $documentation,
            ),
            $call->activeParameter,
        );
    }

    /**
     * @return array{signatures: list<array{label: string, parameters: list<array{label: string}>, documentation?: string}>, activeSignature: int, activeParameter: int}|null
     */
    private function methodCallSignature(PhpInteropCall $call): ?array
    {
        $probe = sprintf('(php/-> %s x', $call->receiver);
        $context = $this->contextResolver->resolve($probe, 1, strlen($probe) + 1);
        if ($context->class === '') {
            return null;
        }

        $info = $this->reflector->methodSignatureInfo($context->class, $call->method);

        return $info instanceof PhpInteropSignature
            ? $this->signatureResponse($info, $call->activeParameter)
            : null;
    }

    private function memberHover(PhpInteropContext $context): ?string
    {
        if ($context->prefix === '') {
            return null;
        }

        $info = $context->kind === PhpInteropContext::KIND_STATIC_MEMBER
            ? $this->reflector->staticMemberInfo($context->class, $context->prefix)
            : $this->reflector->instanceMemberInfo($context->class, $context->prefix);

        return $info instanceof PhpInteropSignature
            ? $this->memberMarkdown(sprintf('%s::%s', $context->class, $context->prefix), $info)
            : null;
    }

    private function functionHover(string $function): ?string
    {
        $info = $this->reflector->functionSignatureInfo($function);

        return $info instanceof PhpInteropSignature
            ? $this->memberMarkdown('php/' . $function, $info)
            : null;
    }

    private function classHover(string $class): ?string
    {
        $info = $this->reflector->classInfo($class);

        return $info instanceof PhpInteropClass ? $this->classMarkdown($info) : null;
    }

    private function memberMarkdown(string $title, PhpInteropSignature $info): string
    {
        return $this->withDoc(
            sprintf("**%s** _(php)_\n\n```php\n%s\n```", $title, $info->label),
            $info->documentation,
        );
    }

    private function classMarkdown(PhpInteropClass $info): string
    {
        $declaration = $info->kind . ' ' . $info->name;
        if ($info->parent !== null) {
            $declaration .= ' extends ' . $info->parent;
        }

        if ($info->interfaces !== []) {
            $declaration .= ' implements ' . implode(', ', $info->interfaces);
        }

        $code = $info->constructor instanceof PhpInteropSignature
            ? sprintf("%s\nnew %s(%s)", $declaration, $info->name, implode(', ', $info->constructor->parameters))
            : $declaration;

        return $this->withDoc(
            sprintf("**\\%s** _(php %s)_\n\n```php\n%s\n```", $info->name, $info->kind, $code),
            $info->documentation,
        );
    }

    private function withDoc(string $markdown, string $documentation): string
    {
        return $documentation === '' ? $markdown : $markdown . "\n\n" . $documentation;
    }

    /**
     * @return array{signatures: list<array{label: string, parameters: list<array{label: string}>, documentation?: string}>, activeSignature: int, activeParameter: int}
     */
    private function signatureResponse(PhpInteropSignature $signature, int $activeParameter): array
    {
        $information = [
            'label' => $signature->label,
            'parameters' => $this->parameterInformation($signature->parameters),
        ];
        if ($signature->documentation !== '') {
            $information['documentation'] = $signature->documentation;
        }

        $count = count($signature->parameters);

        return [
            'signatures' => [$information],
            'activeSignature' => 0,
            'activeParameter' => $count === 0 ? 0 : min($activeParameter, $count - 1),
        ];
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<array{label: string}>
     */
    private function parameterInformation(array $parameters): array
    {
        $information = [];
        foreach ($parameters as $parameter) {
            $information[] = ['label' => $parameter];
        }

        return $information;
    }
}
