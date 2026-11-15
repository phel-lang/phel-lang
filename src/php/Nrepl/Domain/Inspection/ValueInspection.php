<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Inspection;

use Generator;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use Traversable;

use function array_slice;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function strlen;

/**
 * @internal
 */
final class ValueInspection
{
    public const int MAX_DEPTH = 16;

    public const int MAX_ITEMS = 128;

    public const int MAX_HANDLES = 128;

    public const int PAGE_SIZE = 32;

    public const int PREVIEW_BYTES = 96;

    /** @var array<string, InspectionNode> */
    private array $nodes = [];

    /** @var array<int, string> */
    private array $objects = [];

    private readonly string $rootHandle;

    private int $items = 0;

    private bool $busy = false;

    public function __construct(mixed $value)
    {
        $this->rootHandle = $this->retain($value, 0);
    }

    public function id(): string
    {
        return $this->rootHandle;
    }

    /**
     * @return array<string, mixed>
     */
    public function root(): array
    {
        return $this->describe($this->rootHandle);
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $handle, int $offset, int $limit): array
    {
        $node = $this->nodes[$handle] ?? throw new RuntimeException('Unknown or released inspection handle.');
        if ($this->busy) {
            throw new RuntimeException('Inspection is already being paged.');
        }

        if ($offset < 0 || $offset > count($node->children) || $limit < 1 || $limit > self::PAGE_SIZE) {
            throw new RuntimeException('Invalid inspection offset or limit.');
        }

        $this->busy = true;
        try {
            $this->fill($node, $offset + $limit);
            $children = array_slice($node->children, $offset, $limit);
            $next = $offset + count($children);
            return [
                'inspection' => $this->rootHandle,
                'handle' => $handle,
                'children' => $children,
                'next-offset' => $node->complete && $next >= count($node->children) ? -1 : $next,
                'truncated' => $node->reason !== '',
                'reason' => $node->reason,
            ];
        } finally {
            $this->busy = false;
        }
    }

    public function release(): void
    {
        $nodes = $this->nodes;
        $this->nodes = [];

        $this->objects = [];
        foreach ($nodes as $node) {
            try {
                $node->release();
            } catch (Throwable) {
                // A destructor must not prevent releasing the remaining handles.
            }
        }
    }

    private function fill(InspectionNode $node, int $end): void
    {
        while (!$node->complete && count($node->children) < $end) {
            if (!$this->expandable($node->value) || $node->depth >= self::MAX_DEPTH) {
                $node->complete = true;
                $node->reason = $node->depth >= self::MAX_DEPTH ? 'depth' : '';
                break;
            }

            if ($this->items >= self::MAX_ITEMS || count($this->nodes) >= self::MAX_HANDLES) {
                $node->complete = true;
                $node->reason = 'items';
                break;
            }

            $node->entries ??= $this->entries($node->value);
            try {
                if ($node->advance) {
                    $node->entries->next();
                    $node->advance = false;
                }

                if (!$node->entries->valid()) {
                    $node->complete = true;
                    break;
                }

                [$key, $value] = $node->entries->current();
                if ($this->nodes === []) {
                    throw new RuntimeException('Inspection was released while paging.');
                }

                $reference = is_object($value) && isset($this->objects[spl_object_id($value)]);
                $handle = $this->retain($value, $node->depth + 1);
                $node->children[] = ['label' => $this->preview($key), ...$this->describe($handle), 'reference' => $reference];
                ++$this->items;
                $node->advance = true;
            } catch (Throwable) {
                $node->complete = true;
                $node->reason = 'unavailable';
            }
        }
    }

    private function retain(mixed $value, int $depth): string
    {
        if (is_object($value) && isset($this->objects[spl_object_id($value)])) {
            return $this->objects[spl_object_id($value)];
        }

        $handle = bin2hex(random_bytes(16));
        $this->nodes[$handle] = new InspectionNode($value, $depth);
        if (is_object($value)) {
            $this->objects[spl_object_id($value)] = $handle;
        }

        return $handle;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(string $handle): array
    {
        $node = $this->nodes[$handle] ?? throw new RuntimeException('Unknown or released inspection handle.');
        $expandable = $this->expandable($node->value);
        $depthLimit = $expandable && $node->depth >= self::MAX_DEPTH;
        $result = [
            'handle' => $handle,
            'kind' => $this->kind($node->value),
            'preview' => $this->preview($node->value),
            'preview-truncated' => strlen($this->rawPreview($node->value)) > self::PREVIEW_BYTES,
            'expandable' => $expandable && !$depthLimit,
            'truncated' => $depthLimit,
            'reason' => $depthLimit ? 'depth' : '',
        ];
        if (is_resource($node->value)) {
            $result['resource-type'] = get_resource_type($node->value);
            $result['resource-id'] = get_resource_id($node->value);
        }

        return $result;
    }

    private function expandable(mixed $value): bool
    {
        return is_array($value) || (is_object($value) && !$value instanceof Keyword && !$value instanceof Symbol);
    }

    private function kind(mixed $value): string
    {
        return match (true) {
            $value === null => 'nil',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_array($value) => 'array',
            $value instanceof Keyword => 'keyword',
            $value instanceof Symbol => 'symbol',
            $value instanceof TypeInterface && $value instanceof Traversable => 'collection',
            is_object($value) => 'object',
            default => 'resource',
        };
    }

    private function preview(mixed $value): string
    {
        return substr($this->rawPreview($value), 0, self::PREVIEW_BYTES);
    }

    private function rawPreview(mixed $value): string
    {
        return match (true) {
            $value === null => 'nil',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            $value instanceof Keyword, $value instanceof Symbol => $value->getFullName(),
            is_object($value) => $value::class,
            is_array($value) => 'PHP array',
            is_resource($value) => get_resource_type($value),
            default => 'closed resource',
        };
    }

    /**
     * @return Generator<int, array{mixed, mixed}>
     */
    private function entries(mixed $value): Generator
    {
        if (is_array($value) || ($value instanceof TypeInterface && $value instanceof Traversable)) {
            foreach ($value as $key => $child) {
                yield [$key, $child];
            }

            return;
        }

        if (!is_object($value)) {
            return;
        }

        $reflection = new ReflectionObject($value);
        if ($reflection->isUninitializedLazyObject($value)) {
            return;
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->isVirtual() || !$property->isInitialized($value)) {
                continue;
            }

            // Raw access bypasses PHP property hooks.
            yield [$property->getName(), $property->getRawValue($value)];
        }
    }
}
