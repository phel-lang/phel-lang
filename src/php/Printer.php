<?php

declare(strict_types=1);

namespace Phel;

use Exception;
use Phel\Lang\Keyword;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Struct;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Lang\Set;

final class Printer
{
    private bool $readable;

    public static function readable(): self
    {
        return new self(true);
    }

    public static function nonReadable(): self
    {
        return new self(false);
    }

    private function __construct(bool $readable)
    {
        $this->readable = $readable;
    }

    /**
     * Converts a form to a printable string
     *
     * @param mixed $form The form to print.
     */
    public function print($form): string
    {
        if ($form instanceof Tuple) {
            return $this->printTuple($form);
        }
        if ($form instanceof Keyword) {
            return ':' . $form->getName();
        }
        if ($form instanceof Symbol) {
            return $form->getName();
        }
        if ($form instanceof Set) {
            return $this->printSet($form);
        }
        if ($form instanceof PhelArray) {
            return $this->printArray($form);
        }
        if ($form instanceof Struct) {
            return $this->printStruct($form);
        }
        if ($form instanceof Table) {
            return $this->printTable($form);
        }
        if (is_string($form)) {
            return $this->printString($form);
        }
        if (is_int($form) || is_float($form)) {
            return (string) $form;
        }
        if (is_bool($form)) {
            return $form === true ? 'true' : 'false';
        }
        if ($form === null) {
            return 'nil';
        }
        if (is_array($form) && !$this->readable) {
            return '<PHP-Array>';
        }
        if (is_resource($form) && !$this->readable) {
            return '<PHP Resource id #' . (string)$form . '>';
        }
        if (is_object($form) && !$this->readable) {
            return '<PHP-Object(' . get_class($form) . ')>';
        }

        $type = gettype($form);
        if ($type === 'object') {
            $type = get_class($form);
        }
        throw new Exception('Printer can not print this type: ' . $type);
    }

    private function printString(string $str): string
    {
        if (!$this->readable) {
            return $str;
        }
        $ret = '"';
        for ($i = 0, $l = strlen($str); $i < $l; ++$i) {
            $o = ord($str[$i]);
            switch (true) {
                case $o === 9: $ret .= '\t'; break;
                case $o === 10: $ret .= '\n'; break;
                case $o === 11: $ret .= '\v'; break;
                case $o === 12: $ret .= '\f'; break;
                case $o === 13: $ret .= '\r'; break;
                case $o === 27: $ret .= '\e'; break;
                case $o === 36: $ret .= '\$'; break;
                case $o === 34: $ret .= '\"'; break;
                case $o === 92: $ret .= '\\\\'; break;
                case $o >= 32 && $o <= 127:
                    // characters U-00000000 - U-0000007F (same as ASCII)
                    $ret .= $str[$i];
                    break;
                case ($o & 0xE0) === 0xC0:
                    // characters U-00000080 - U-000007FF, mask 110XXXXX
                    $hex = $this->utf8ToUnicodePoint(substr($str, $i, 2));
                    $i += 1;
                    $ret .= sprintf('\u{%04s}', $hex);
                    break;
                case ($o & 0xF0) === 0xE0:
                    // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                    $hex = $this->utf8ToUnicodePoint(substr($str, $i, 3));
                    $i += 2;
                    $ret .= sprintf('\u{%04s}', $hex);
                    break;
                case ($o & 0xF8) === 0xF0:
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

    private function utf8ToUnicodePoint(string $s): string
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

    private function printTuple(Tuple $form): string
    {
        $prefix = $form->isUsingBracket() ? '[' : '(';
        $suffix = $form->isUsingBracket() ? ']' : ')';

        $args = [];
        foreach ($form as $elem) {
            $args[] = $this->print($elem);
        }

        return $prefix . implode(' ', $args) . $suffix;
    }

    private function printStruct(Struct $form): string
    {
        $prefix = '(' . get_class($form) . ' ';
        $suffix = ')';
        $args = [];
        foreach ($form->getAllowedKeys() as $key) {
            $args[] = $this->print($form[$key]);
        }

        return $prefix . implode(' ', $args) . $suffix;
    }

    private function printArray(PhelArray $form): string
    {
        $prefix = '@[';
        $suffix = ']';

        $args = [];
        foreach ($form as $elem) {
            $args[] = $this->print($elem);
        }

        return $prefix . implode(' ', $args) . $suffix;
    }

    private function printTable(Table $form): string
    {
        $prefix = '@{';
        $suffix = '}';

        $args = [];
        foreach ($form as $key => $value) {
            $args[] = $this->print($key);
            $args[] = $this->print($value);
        }

        return $prefix . implode(' ', $args) . $suffix;
    }

    private function printSet(Set $form): string
    {
        $prefix = '(set';
        $suffix = ')';

        $args = [];
        foreach ($form as $elem) {
            $args[] = $this->print($elem);
        }

        return $prefix . (count($args) > 0 ? ' ' : '') . implode(' ', $args) . $suffix;
    }
}
