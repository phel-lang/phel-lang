<?php

namespace Phel;

use Phel\Exceptions\ReaderException;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Stream\CharStream;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\Stream\CharData;
use Phel\Stream\SourceLocation;

class Reader {

    private $syntax = [
        '(', ')', '[', ']', '{', '}', '\'', ',', '`'
    ];

    private $stringReplacements = [
        '\\' => '\\',
        '$'  =>  '$',
        'n'  => "\n",
        'r'  => "\r",
        't'  => "\t",
        'f'  => "\f",
        'v'  => "\v",
        'e'  => "\x1B",
    ];


    /**
     * @var SourceLocation | null
     */
    private $startLocation = null;

    /**
     * @var SourceLocation | null
     */
    private $lastLocation = null;
    
    /**
     * @var string
     */
    private $readChars = '';
    
    public function read(CharStream $stream, $eof = null) {
        $this->eatwhite($stream);

        $this->readChars = '';
        $this->startLocation = null;
        $this->lastLocation = null;

        if ($stream->peek()) {
            $this->startLocation = $stream->peek()->getLocation();
        }
        $ast = $this->rdex($stream, $eof);

        return new ReaderResult(
            $ast,
            $this->startLocation,
            $this->lastLocation,
            $this->readChars
        );
    }

    private function rdex(CharStream $stream, $eof = null) {
        $this->eatwhite($stream);

        $c = $this->readStream($stream);
        if (!$c) {
            return $eof;
        }

        switch ($c->getChar()) {
            case '(':
                return $this->withSourceLocation($c, fn() => $this->rdlist($stream, ')'));
            case '[':
                return $this->withSourceLocation($c, fn() => $this->rdlist($stream, ']'));
            case ')':
            case ']':
                throw new ReaderException('unexpected terminator', $this->startLocation, $this->lastLocation, $this->readChars);

            case '\'':
                return $this->withSourceLocation($c, fn() => $this->rdwrap($stream, "quote"));
            case '`':
                return $this->withSourceLocation($c, fn() => $this->rdquasiquote($stream));
            case ',':
                return $this->withSourceLocation($c, function() use ($stream) {
                    if ($stream->peek()->getChar() == "@") {
                        $this->readStream($stream);
                        return $this->rdwrap($stream, "unquote-splicing");
                    } else {
                        return $this->rdwrap($stream, "unquote");
                    }
                });

            case '"':
                return $this->parseEscapedString($this->rddelim($stream, '"'));
                
            case '@':
                if ($stream->peek()->getChar() == "[") {
                    $this->readStream($stream);
                    return $this->withSourceLocation($c, fn() => Tuple::create(new Symbol('array'), ...$this->rdlist($stream, ']')));
                } else if ($stream->peek()->getChar() == "{") {
                    $this->readStream($stream);
                    $xs = $this->rdlist($stream, "}");
                    if ($xs instanceof Tuple && count($xs) % 2 === 0) {
                        $table = Tuple::create(new Symbol('table'), ...$xs);
                        $endLocation = $this->lastLocation;

                        $table->setStartLocation($c->getLocation());
                        $table->setEndLocation($endLocation);

                        return $table;
                    } else {
                        throw new ReaderException("Tables must have an even number of parameters", $this->startLocation, $this->lastLocation, $this->readChars);
                    }
                    
                } else {
                    throw new ReaderException("unexpected symbol. expected [ oder {", $this->startLocation, $this->lastLocation, $this->readChars);
                }
                
            default:
                $word = $this->rdword($stream, $c->getChar());
                if ($word instanceof Phel) {
                    return $this->withSourceLocation($c, fn() => $word);
                } else {
                    return $word;
                }
                
        }
    }

    private function rdquasiquote($stream) {
        $e = $this->hardRdex($stream, "missing expression");
        $q = new Quasiquote();
        return $q->quasiquote($e);
    }

    private function withSourceLocation(CharData $c, callable $f) {
        $startLocation = $c->getLocation();
        $result = $f();
        $endLocation = $this->lastLocation;

        $result->setStartLocation($startLocation);
        $result->setEndLocation($endLocation);

        return $result;
    }

    private function rdword($stream, string $c) {
        return $this->parseword(
            $c . $this->charstil($stream, function($c) { return $this->breakc($c); })
        );
    }

    private function parseword(string $word) {
        if (preg_match("/([+-])?0[bB][01]+(_[01]+)*/", $word, $matches)) {
            // binary numbers
            $sign = (isset($matches[1]) && $matches[1] == '-') ? -1 : 1;
            return $sign * bindec(str_replace('_', '', $word));
        } else if (preg_match("/([+-])?0[xX][0-9a-fA-F]+(_[0-9a-fA-F]+)*/", $word, $matches)) {
            $sign = (isset($matches[1]) && $matches[1] == '-') ? -1 : 1;
            // hexdecimal numbers
            return $sign = hexdec(str_replace('_', '', $word));
        } else if (preg_match("/([+-])?0[0-7]+(_[0-7]+)*/", $word, $matches)) {
            $sign = (isset($matches[1]) && $matches[1] == '-') ? -1 : 1;
            // octal numbers
            return $sign * octdec(str_replace('_', '', $word));
        } else if (is_numeric($word)) {
            return $word + 0;
        } else {
            return $this->sym($word);
        }
    }

    private function sym($name) {
        switch ($name) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'nil':
                return null;
            default:
                if ($name[0] == ':') {
                    return new Keyword(substr($name, 1));
                } else {
                    return new Symbol($name);
                }
        }
    }

    private function rdlist($stream, $term, $acc = []) {
        $this->eatwhite($stream);

        $cData = $stream->peek();
        if ($cData === false) {
            throw new ReaderException('unterminated list', $this->startLocation, $this->lastLocation, $this->readChars);
        } else if ($cData->getChar() == $term) {
            $this->readStream($stream);
            if ($term == ')') {
                return new Tuple($acc, false);
            } else if ($term == ']' || $term = '}') {
                return new Tuple($acc, true);
            } else {
                throw new ReaderException('unterminted list', $this->startLocation, $this->lastLocation, $this->readChars);
            }
        } else {
            $e = $this->rdex($stream);
            return $this->rdlist($stream, $term, array_merge($acc, [$e]));
        }
    }

    private function rddelim($stream, $delimiter, $esc = false) {
        $acc = "";
        $esc = false;
        while (true) {
            $cData = $this->readStream($stream);
            if ($cData === false) {
                throw new ReaderException('missing delimiter', $this->startLocation, $this->lastLocation, $this->readChars);
            } else if ($esc) {
                $esc = false;
                $acc .= $cData->getChar();
            } else if ($cData->getChar() == "\\") {
                $esc = true;
                $acc .= $cData->getChar();
            } else if ($cData->getChar() == $delimiter) {
                return $acc;
            } else {
                $acc .= $cData->getChar();
            }
        }
    }

    private function whitec(string $char): bool {
        return in_array($char, [" ", "\n", "\t", "\r"]);
    }

    private function breakc(string $char): bool {
        return $char === False || $this->whitec($char) || $char == "#" || in_array($char, $this->syntax);
    }

    private function eatwhite(CharStream $stream) {
        while (true) {
            $cData = $stream->peek();
            if ($cData === false) {
                break;
            } else if ($cData->getChar() == "#") {
                $this->charstil($stream, function($char) { return $char == "\n"; });
            } else if ($this->whitec($cData->getChar())) {
                $this->readStream($stream);
            } else {
                break;
            }
        }
    }

    private function charstil(CharStream $stream, $testFn) {
        $cData = $stream->peek();
        $buf = "";
        while (!($cData === FALSE || $testFn($cData->getChar()))) {
            $buf .= $this->readStream($stream)->getChar();
            $cData = $stream->peek();
        }

        return $buf;
    }

    private function rdwrap($stream, $token) {
        $e = $this->hardRdex($stream, "missing expression");
        return new Tuple([new Symbol($token), $e]);
    }

    private function hardRdex($stream, $msg) {
        $eof = null;
        $v = $this->rdex($stream, $eof);
        if ($v == $eof) {
            throw new ReaderException($msg, $this->startLocation, $this->lastLocation, $this->readChars);
        } else {
            return $v;
        }
    }

    private function readStream(CharStream $stream) {
        if ($stream->peek()) {
            $this->lastLocation = $stream->peek()->getLocation();
        }

        $res = $stream->read();

        if ($res) {
            $this->readChars .= $res->getChar();
        }

        return $res;
    }

    private function parseEscapedString($str) {
        $str = str_replace('\\"', '"', $str);

        return preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            function($matches) {
                $str = $matches[1];

                if (isset($this->stringReplacements[$str])) {
                    return $this->stringReplacements[$str];
                } elseif ('x' === $str[0] || 'X' === $str[0]) {
                    return chr(hexdec(substr($str, 1)));
                } elseif ('u' === $str[0]) {
                    return self::codePointToUtf8(hexdec($matches[2]));
                } else {
                    return chr(octdec($str));
                }
            },
            $str
        );
    }

    private function codePointToUtf8(int $num) : string {
        if ($num <= 0x7F) {
            return chr($num);
        }
        if ($num <= 0x7FF) {
            return chr(($num>>6) + 0xC0) . chr(($num&0x3F) + 0x80);
        }
        if ($num <= 0xFFFF) {
            return chr(($num>>12) + 0xE0) . chr((($num>>6)&0x3F) + 0x80) . chr(($num&0x3F) + 0x80);
        }
        if ($num <= 0x1FFFFF) {
            return chr(($num>>18) + 0xF0) . chr((($num>>12)&0x3F) + 0x80)
                 . chr((($num>>6)&0x3F) + 0x80) . chr(($num&0x3F) + 0x80);
        }
        throw new ReaderException('Invalid UTF-8 codepoint escape sequence: Codepoint too large', $this->startLocation, $this->lastLocation, $this->readChars);
    }

}