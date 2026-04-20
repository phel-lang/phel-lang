<?php

declare(strict_types=1);

namespace Phel\Lang;

use function array_key_exists;
use function array_keys;
use function is_callable;

/**
 * Global registry for reader tagged-literal handlers.
 *
 * A handler is a callable `fn(mixed $form): mixed` applied by the reader
 * when it encounters `#tag <form>`. The value returned by the handler
 * replaces the tagged literal in the read output.
 *
 * Last registration wins: re-registering an existing tag overwrites its
 * handler.
 */
final class TagRegistry
{
    /** @var array<string, callable> */
    private array $handlers = [];

    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(string $tag, callable $handler): void
    {
        $this->handlers[$tag] = $handler;
    }

    public function unregister(string $tag): void
    {
        unset($this->handlers[$tag]);
    }

    public function has(string $tag): bool
    {
        return array_key_exists($tag, $this->handlers);
    }

    public function get(string $tag): ?callable
    {
        $handler = $this->handlers[$tag] ?? null;
        return is_callable($handler) ? $handler : null;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        $tags = array_keys($this->handlers);
        sort($tags);
        return $tags;
    }

    public function clear(): void
    {
        $this->handlers = [];
    }
}
