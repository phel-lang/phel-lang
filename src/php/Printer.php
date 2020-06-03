<?php

namespace Phel;

use Exception;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\Struct;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

class Printer {

    /**
     * Converts a form to a printable string
     * 
     * @param Phel|scalar|null $form The form to print.
     * @param bool $readable Print in readable format or not.
     * 
     * @return string
     */
    public function print($form, bool $readable): string {
        if ($form instanceof Tuple) {
            return $this->printTuple($form, $readable);
        } else if ($form instanceof Keyword) {
            return ':' . $form->getName();
        } else if ($form instanceof Symbol) {
            return $form->getName();
        } else if ($form instanceof PhelArray) {
            return $this->printArray($form, $readable);
        } else if ($form instanceof Struct) {
            return $this->printStruct($form, $readable);
        } else if ($form instanceof Table) {
            return $this->printTable($form, $readable);
        } else if (is_string($form)) {
            return $this->printString($form, $readable);
        } else if (is_int($form) || is_float($form)) {
            return (string) $form;
        } else if (is_bool($form)) {
            return $form === true ? 'true' : 'false';
        } else if ($form === null) {
            return 'nil';
        } else if (is_array($form) && !$readable) {
            return "<PHP-Array>";
        } else if (is_resource($form) && !$readable) {
            return "<PHP Resource id #" . $form . '>';
        } else if (is_object($form) && !$readable) {
            return '<PHP-Object(' . get_class($form) . ')>';
        } else {
            $type = gettype($form);
            if ($type == 'object') {
                $type = get_class($form);
            }
            throw new Exception("Printer can not print this type: " . $type);
        }
    }

    public function printString(string $str, bool $readable): string {
        if (!$readable) {
            return $str;
        } else {
            $ret = '"';
            for ($i = 0, $l = strlen($str); $i < $l; ++$i) {
                $o = ord($str[$i]);
                switch (true) {
                    case $o == 9: $ret .= '\t'; break;
                    case $o == 10: $ret .= '\n'; break;
                    case $o == 11: $ret .= '\v'; break;
                    case $o == 12: $ret .= '\f'; break;
                    case $o == 13: $ret .= '\r'; break;
                    case $o == 27: $ret .= '\e'; break;
                    case $o == 36: $ret .= '\$'; break;
                    case $o == 34: $ret .= '\"'; break;
                    case $o == 92: $ret .= '\\\\'; break;
                    case $o >= 32 && $o <= 127:
                        // characters U-00000000 - U-0000007F (same as ASCII)
                        $ret .= $str[$i];
                        break;
                    case ($o & 0xE0) == 0xC0:
                        // characters U-00000080 - U-000007FF, mask 110XXXXX
                        $hex = $this->utf8ToUnicodePoint(substr($str, $i, 2));
                        $i += 1;
                        $ret .= sprintf('\u{%04s}', $hex);
                        break;
                    case ($o & 0xF0) == 0xE0:
                        // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                        $hex = $this->utf8ToUnicodePoint(substr($str, $i, 3));
                        $i += 2;
                        $ret .= sprintf('\u{%04s}', $hex);
                        break;
                    case ($o & 0xF8) == 0xF0:
                        // characters U-00010000 - U-001FFFFF, mask 11110XXX
                        $hex = $this->utf8ToUnicodePoint(substr($str, $i, 4));
                        $i += 3;
                        $ret .= sprintf('\u{%04s}', $hex);
                        break;
                    case $o < 31 || $o > 126: 
                        $ret .= '\x' . str_pad(dechex($o), 2, '0', STR_PAD_LEFT);
                        break;
                    default: 
                        $ret .= $str[$i];
                }
            }
            return $ret . '"';
        }
    }

    public function utf8ToUnicodePoint(string $s): string
    {
        $a = ($s = unpack('C*', $s)) ? $s[1] : 0;
        if (0xF0 <= $a) {
            return dechex((($a - 0xF0) << 18) + (($s[2] - 0x80) << 12) + (($s[3] - 0x80) << 6) + $s[4] - 0x80);
        }
        if (0xE0 <= $a) {
            return dechex((($a - 0xE0) << 12) + (($s[2] - 0x80) << 6) + $s[3] - 0x80);
        }
        if (0xC0 <= $a) {
            return dechex((($a - 0xC0) << 6) + $s[2] - 0x80);
        }

        return (string) $a;
    }


    private function printTuple(Tuple $form, bool $readable): string {
        $prefix = $form->isUsingBracket() ? '[' : '(';
        $suffix = $form->isUsingBracket() ? ']' : ')';

        $args = [];
        foreach ($form as $elem) {
            $args[] = $this->print($elem, $readable);
        }

        return $prefix . implode(" ", $args) . $suffix;
    }

    private function printStruct(Struct $form, bool $readable): string {
        $prefix = '(' . get_class($form) . ' ';
        $suffix = ')';
        $args = [];
        foreach ($form->getAllowedKeys() as $key) {
            $args[] = $this->print($form[$key], $readable);
        }
        
        return $prefix . implode(" ", $args) . $suffix;
    }

    private function printArray(PhelArray $form, bool $readable): string {
        $prefix = '@[';
        $suffix = ']';

        $args = [];
        foreach ($form as $elem) {
            $args[] = $this->print($elem, $readable);
        }

        return $prefix . implode(" ", $args) . $suffix;
    }

    private function printTable(Table $form, bool $readable): string {
        $prefix = '@{';
        $suffix = '}';

        $args = [];
        foreach ($form as $key => $value) {
            $args[] = $this->print($key, $readable);
            $args[] = $this->print($value, $readable);
        }

        return $prefix . implode(" ", $args) . $suffix;
    }
}