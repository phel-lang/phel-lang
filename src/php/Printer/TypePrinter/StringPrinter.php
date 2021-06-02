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
    private bool $withColor;

    public function __construct(bool $readable, bool $withColor = false)
    {
        $this->readable = $readable;
        $this->withColor = $withColor;
    }

    /**
     * @param string $str
     * @param mixed $form
     */
    public function print($form): string
    {
        $str = $this->parseString($form);

        return $this->color($str);
    }

    private function parseString(string $str): string
    {
        if (!$this->readable) {
            return $str;
        }

        return $this->readCharacters($str);
    }

    private function readCharacters(string $str): string
    {
        $return = '';
        for ($index = 0, $length = strlen($str); $index < $length; ++$index) {
            $asciiChar = ord($str[$index]);

            if (isset(self::SPECIAL_CHARACTERS[$asciiChar])) {
                $return .= self::SPECIAL_CHARACTERS[$asciiChar];
                continue;
            }

            if ($this->isAsciiCharacter($asciiChar)) {
                $return .= $str[$index];
                continue;
            }

            if ($this->isMask110XXXXX($asciiChar)) {
                $hex = $this->utf8ToUnicodePoint(substr($str, $index, 2));
                $return .= sprintf('\u{%04s}', $hex);
                $index++;
                continue;
            }

            if ($this->isMask1110XXXX($asciiChar)) {
                $hex = $this->utf8ToUnicodePoint(substr($str, $index, 3));
                $return .= sprintf('\u{%04s}', $hex);
                $index += 2;
                continue;
            }

            if ($this->isMask11110XXX($asciiChar)) {
                $hex = $this->utf8ToUnicodePoint(substr($str, $index, 4));
                $return .= sprintf('\u{%04s}', $hex);
                $index += 3;
                continue;
            }

            if ($asciiChar < 31 || $asciiChar > 126) {
                $return .= '\x' . str_pad(dechex($asciiChar), 2, '0', STR_PAD_LEFT);
                continue;
            }

            $return .= $str[$index];
        }

        return '"' . $return . '"';
    }

    /**
     * Characters U-00000000 - U-0000007F (same as ASCII).
     */
    private function isAsciiCharacter(int $asciiChar): bool
    {
        return $asciiChar >= 32 && $asciiChar <= 127;
    }

    /**
     * Characters U-00000080 - U-000007FF, mask 110XXXXX.
     */
    private function isMask110XXXXX(int $asciiChar): bool
    {
        return ($asciiChar & 0xE0) === 0xC0;
    }

    /**
     * Characters U-00000800 - U-0000FFFF, mask 1110XXXX.
     */
    private function isMask1110XXXX(int $asciiChar): bool
    {
        return ($asciiChar & 0xF0) === 0xE0;
    }

    /**
     * Characters U-00010000 - U-001FFFFF, mask 11110XXX.
     */
    private function isMask11110XXX(int $asciiChar): bool
    {
        return ($asciiChar & 0xF8) === 0xF0;
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

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;95m%s\033[0m", $str);
        }

        return $str;
    }
}
