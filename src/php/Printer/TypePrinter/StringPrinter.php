<?php

declare(strict_types=1);

namespace Phel\Printer\TypePrinter;

use function ord;
use function sprintf;
use function strlen;

/**
 * @implements TypePrinterInterface<string>
 */
final readonly class StringPrinter implements TypePrinterInterface
{
    private const array SPECIAL_CHARACTERS = [
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

    public function __construct(
        private bool $readable,
        private bool $withColor = false,
    ) {
    }

    public static function nonReadable(bool $withColor = false): self
    {
        return new self(readable: false, withColor: $withColor);
    }

    public static function readable(bool $withColor = false): self
    {
        return new self(readable: true, withColor: $withColor);
    }

    public function print(mixed $form): string
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
                $return .= substr($str, $index, 2);
                ++$index;
                continue;
            }

            if ($this->isMask1110XXXX($asciiChar)) {
                $return .= substr($str, $index, 3);
                $index += 2;
                continue;
            }

            if ($this->isMask11110XXX($asciiChar)) {
                $return .= substr($str, $index, 4);
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

    private function color(string $str): string
    {
        if ($this->withColor) {
            return sprintf("\033[0;95m%s\033[0m", $str);
        }

        return $str;
    }
}
