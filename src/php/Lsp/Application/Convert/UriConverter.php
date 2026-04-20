<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;

/**
 * Bi-directional file URI <-> filesystem path conversion, with a tolerant
 * stance on path-style URIs so editors that accept either form keep working.
 */
final class UriConverter
{
    public function toFilePath(string $uri): string
    {
        if (str_starts_with($uri, 'file://')) {
            $parsed = parse_url($uri);
            if ($parsed !== false && isset($parsed['path'])) {
                return rawurldecode($parsed['path']);
            }
        }

        return $uri;
    }

    public function fromFilePath(string $path): string
    {
        if (str_starts_with($path, 'file://')) {
            return $path;
        }

        $normalized = str_replace('\\', '/', $path);

        if (preg_match('/^[A-Za-z]:\//', $normalized)) {
            // Windows drive: file:///C:/path
            return 'file:///' . $this->encodePath($normalized);
        }

        if (!str_starts_with($normalized, '/')) {
            $normalized = '/' . $normalized;
        }

        return 'file://' . $this->encodePath($normalized);
    }

    public function isFileUri(string $uri): bool
    {
        $prefix = 'file://';
        return strlen($uri) >= strlen($prefix) && strtolower(substr($uri, 0, strlen($prefix))) === $prefix;
    }

    private function encodePath(string $path): string
    {
        $encoded = preg_replace_callback('/[^A-Za-z0-9_\-.~\/:]/', static fn(array $matches): string => rawurlencode($matches[0]), $path);

        return $encoded ?? $path;
    }
}
