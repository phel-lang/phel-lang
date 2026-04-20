<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Bencode;

use function ctype_digit;
use function sprintf;
use function strlen;
use function substr;

final class BencodeDecoder
{
    /**
     * Decode a bencode byte string into a PHP value.
     *
     * Integers become PHP int; byte strings become PHP string; lists become
     * list<mixed>; dictionaries become associative array<string, mixed>.
     *
     * The whole input must be a single valid bencode value.
     */
    public function decode(string $input): mixed
    {
        $position = 0;
        $value = $this->decodeAt($input, $position);

        if ($position !== strlen($input)) {
            throw BencodeException::invalidToken(sprintf('trailing bytes at %d', $position), $position);
        }

        return $value;
    }

    /**
     * Decode a bencode value starting at offset 0 and return the value plus
     * the number of bytes consumed. Useful for streaming/framed parsing.
     *
     * @return array{0: mixed, 1: int}
     */
    public function decodeWithLength(string $input): array
    {
        $position = 0;
        $value = $this->decodeAt($input, $position);

        return [$value, $position];
    }

    private function decodeAt(string $input, int &$position): mixed
    {
        if ($position >= strlen($input)) {
            throw BencodeException::unexpectedEndOfInput($position);
        }

        $marker = $input[$position];

        if ($marker === 'i') {
            return $this->decodeInteger($input, $position);
        }

        if ($marker === 'l') {
            return $this->decodeList($input, $position);
        }

        if ($marker === 'd') {
            return $this->decodeDict($input, $position);
        }

        if (ctype_digit($marker)) {
            return $this->decodeString($input, $position);
        }

        throw BencodeException::invalidToken($marker, $position);
    }

    private function decodeInteger(string $input, int &$position): int
    {
        // skip 'i'
        ++$position;
        $end = $this->indexOf($input, 'e', $position);
        $raw = substr($input, $position, $end - $position);

        if ($raw === '' || $raw === '-') {
            throw BencodeException::invalidInteger($raw, $position);
        }

        if ($raw === '-0') {
            throw BencodeException::invalidInteger($raw, $position);
        }

        if (strlen($raw) > 1 && $raw[0] === '0') {
            throw BencodeException::invalidInteger($raw, $position);
        }

        $start = $raw[0] === '-' ? 1 : 0;
        for ($i = $start; $i < strlen($raw); ++$i) {
            if (!ctype_digit($raw[$i])) {
                throw BencodeException::invalidInteger($raw, $position);
            }
        }

        $position = $end + 1;

        return (int) $raw;
    }

    private function decodeString(string $input, int &$position): string
    {
        $colon = $this->indexOf($input, ':', $position);
        $lengthRaw = substr($input, $position, $colon - $position);

        if ($lengthRaw === '' || !ctype_digit($lengthRaw)) {
            throw BencodeException::invalidStringLength($lengthRaw, $position);
        }

        $length = (int) $lengthRaw;
        $start = $colon + 1;
        if ($start + $length > strlen($input)) {
            throw BencodeException::unexpectedEndOfInput($start + $length);
        }

        $value = substr($input, $start, $length);
        $position = $start + $length;

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function decodeList(string $input, int &$position): array
    {
        // skip 'l'
        ++$position;
        $list = [];

        while (true) {
            if ($position >= strlen($input)) {
                throw BencodeException::unexpectedEndOfInput($position);
            }

            if ($input[$position] === 'e') {
                ++$position;
                return $list;
            }

            $list[] = $this->decodeAt($input, $position);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDict(string $input, int &$position): array
    {
        // skip 'd'
        ++$position;
        $dict = [];

        while (true) {
            if ($position >= strlen($input)) {
                throw BencodeException::unexpectedEndOfInput($position);
            }

            if ($input[$position] === 'e') {
                ++$position;
                return $dict;
            }

            if (!ctype_digit($input[$position])) {
                throw BencodeException::invalidToken($input[$position], $position);
            }

            $key = $this->decodeString($input, $position);
            $dict[$key] = $this->decodeAt($input, $position);
        }
    }

    private function indexOf(string $haystack, string $needle, int $from): int
    {
        $length = strlen($haystack);
        for ($i = $from; $i < $length; ++$i) {
            if ($haystack[$i] === $needle) {
                return $i;
            }
        }

        throw BencodeException::unexpectedEndOfInput($length);
    }
}
