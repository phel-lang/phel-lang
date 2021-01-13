<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

/**
 * @implements TypePrinterInterface<string>
 */
final class StringPrinter implements TypePrinterInterface
{
    private const SPECIAL_CHARACTERS = [
        9 => '\t',
        10 => '\n',
        11 => '\v',
        12 => '\f',
        13 => '\r',
        27 => '\e',
        34 => '\"',
        36 => '\$',
        92 => '\\\\',
    ];

    private bool $readable;

    public function __construct(bool $readable)
    {
        $this->readable = $readable;
    }

    /**
     * @param string $str
     */
    public function print($str): string
    {
        if (!$this->readable) {
            return $str;
        }

        return $this->readCharacters($str);
    }

    private function readCharacters(string $str): string
    {
        $ret = '';
        for ($i = 0, $l = strlen($str); $i < $l; ++$i) {
            $o = ord($str[$i]);

            if (isset(self::SPECIAL_CHARACTERS[$o])) {
                $ret .= self::SPECIAL_CHARACTERS[$o];
                continue;
            }

            if ($this->isAsciiCharacter($o)) {
                $ret .= $str[$i];
                continue;
            }

            if ($this->isMask110XXXXX($o)) {
                $hex = $this->utf8ToUnicodePoint(substr($str, $i, 2));
                ++$i;
                $ret .= sprintf('\u{%04s}', $hex);
                continue;
            }

            if ($this->isMask1110XXXX($o)) {
                $hex = $this->utf8ToUnicodePoint(substr($str, $i, 3));
                $i += 2;
                $ret .= sprintf('\u{%04s}', $hex);
                continue;
            }

            if ($this->isMask11110XXX($o)) {
                $hex = $this->utf8ToUnicodePoint(substr($str, $i, 4));
                $i += 3;
                $ret .= sprintf('\u{%04s}', $hex);
                continue;
            }

            if ($o < 31 || $o > 126) {
                $ret .= '\x' . str_pad(dechex($o), 2, '0', STR_PAD_LEFT);
                continue;
            }

            $ret .= $str[$i];
        }

        return '"' . $ret . '"';
    }

    /**
     * Characters U-00000000 - U-0000007F (same as ASCII).
     */
    private function isAsciiCharacter(int $character): bool
    {
        return $character >= 32 && $character <= 127;
    }

    /**
     * Characters U-00000080 - U-000007FF, mask 110XXXXX.
     */
    private function isMask110XXXXX(int $character): bool
    {
        return ($character & 0xE0) === 0xC0;
    }

    private function utf8ToUnicodePoint(string $str): string
    {
        $a = ($str = unpack('C*', $str)) ? ((int) $str[1]) : 0;
        if (0xF0 <= $a) {
            return dechex((($a - 0xF0) << 18) + ((((int) $str[2]) - 0x80) << 12) + ((((int) $str[3]) - 0x80) << 6) + ((int) $str[4]) - 0x80);
        }
        if (0xE0 <= $a) {
            return dechex((($a - 0xE0) << 12) + ((((int) $str[2]) - 0x80) << 6) + ((int) $str[3]) - 0x80);
        }
        if (0xC0 <= $a) {
            return dechex((($a - 0xC0) << 6) + ((int) $str[2]) - 0x80);
        }

        return (string) $a;
    }

    /**
     * Characters U-00000800 - U-0000FFFF, mask 1110XXXX.
     */
    private function isMask1110XXXX(int $character): bool
    {
        return ($character & 0xF0) === 0xE0;
    }

    /**
     * Characters U-00010000 - U-001FFFFF, mask 11110XXX.
     */
    private function isMask11110XXX(int $character): bool
    {
        return ($character & 0xF8) === 0xF0;
    }
}
