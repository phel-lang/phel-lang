<?php

namespace Phel\SourceMap;

use Exception;

class VLQ {

    private $integerToChar = [];
    private $charToInteger = [];

    public function __construct()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
        $charLength = strlen($chars);
        for ($i = 0; $i < $charLength; $i++) {
            $c = $chars[$i];
            $this->integerToChar[$i] = $c;
            $this->charToInteger[$c] = $i;
        }
    }

    public function decode(string $string) {
        $result = [];
        $shift = 0;
        $value = 0;
        $strlen = strlen($string);

        for ($i = 0; $i < $strlen; $i++) {
            $char = $string[$i];
            if (!isset($this->charToInteger[$char])) {
                throw new Exception("Invalid character: " . $char);
            }

            $integer = $this->charToInteger[$char];
            $hasContinuationBit = $integer & 32;

            $integer &= 31;
            $value += $integer << $shift;

            if ($hasContinuationBit) {
                $shift += 5;
            } else {
                $shouldNegate = $value & 1;
                $value = $this->bitShiftRightWithZero($value, 1);

                if ($shouldNegate) {
                    $result[] = ($value === 0 ? -0x80000000 : -$value);
                } else {
                    $result[] = $value;
                }

                $value = $shift = 0;
            }
        }

        return $result;
    }

    public function encodeIntegers(array $numbers) {
        $result = "";
        foreach ($numbers as $number) {
            $result .= $this->encodeInteger($number);
        }
        return $result;
    }

    public function encodeInteger(int $num) {
        $result = "";

        if ($num < 0) {
            $num = (-$num << 1) | 1;
        } else {
            $num <<= 1;
        }

        do {
            $clamped = $num & 31;
            $num = $this->bitShiftRightWithZero($num, 5);

            if ($num > 0) {
                $clamped |= 32;
            }

            $result .= $this->integerToChar[$clamped];
        } while ($num > 0);

        return $result;
    }

    private function bitShiftRightWithZero(int $v, int $n) {
        return ($v & 0xFFFFFFFF) >> ($n & 0x1F);
    }
}