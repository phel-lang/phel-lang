<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Bencode;

use function array_is_list;
use function array_keys;
use function get_debug_type;
use function gettype;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function ksort;
use function sprintf;
use function strlen;

final class BencodeEncoder
{
    /**
     * Encode a PHP value to a bencode byte string.
     *
     * Supported types:
     *   - int   → iNNNe
     *   - string → N:xxxx (binary-safe, byte length)
     *   - list array → l...e
     *   - associative array → d...e (keys sorted lexicographically, must be strings)
     *   - bool → encoded as 1 / 0 integers (nREPL convention for :done status etc.)
     */
    public function encode(mixed $value): string
    {
        if (is_int($value)) {
            return sprintf('i%de', $value);
        }

        if (is_bool($value)) {
            return sprintf('i%de', $value ? 1 : 0);
        }

        if (is_string($value)) {
            return sprintf('%d:%s', strlen($value), $value);
        }

        if (is_array($value)) {
            if ($value === []) {
                // Ambiguous between list and dict — choose list as per bencode tradition.
                return 'le';
            }

            if (array_is_list($value)) {
                return $this->encodeList($value);
            }

            return $this->encodeDict($value);
        }

        throw BencodeException::unsupportedType(get_debug_type($value));
    }

    /**
     * @param list<mixed> $list
     */
    private function encodeList(array $list): string
    {
        $out = 'l';
        foreach ($list as $item) {
            $out .= $this->encode($item);
        }

        return $out . 'e';
    }

    /**
     * @param array<array-key, mixed> $dict
     */
    private function encodeDict(array $dict): string
    {
        // Bencode mandates string keys sorted lexicographically as raw bytes.
        $stringKeyed = [];
        foreach (array_keys($dict) as $key) {
            if (!is_string($key)) {
                throw BencodeException::dictKeyMustBeString(gettype($key));
            }

            $stringKeyed[$key] = $dict[$key];
        }

        ksort($stringKeyed, SORT_STRING);

        $out = 'd';
        foreach ($stringKeyed as $key => $val) {
            $out .= sprintf('%d:%s', strlen($key), $key);
            $out .= $this->encode($val);
        }

        return $out . 'e';
    }
}
