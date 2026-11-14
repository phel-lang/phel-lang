<?php

declare(strict_types=1);

namespace Phel\Shared\SourceMap;

/**
 * Source-path comments are opaque; encode/decode preserves the file and directory flag.
 */
final class SourcePathMarker
{
    private const string PREFIX = '/* phel-source-';

    private function __construct() {}

    public static function encode(string $file, bool $directory): string
    {
        return self::PREFIX . ($directory ? 'directory:' : 'file:') . base64_encode($file) . ' */';
    }

    /**
     * @return array{file: string, directory: bool}|null
     */
    public static function decode(string $comment): ?array
    {
        if (preg_match('~^/\* phel-source-(file|directory):([A-Za-z0-9+/=]*) \*/$~D', $comment, $matches) !== 1) {
            return null;
        }

        $file = base64_decode($matches[2], true);

        return $file === false ? null : ['file' => $file, 'directory' => $matches[1] === 'directory'];
    }
}
