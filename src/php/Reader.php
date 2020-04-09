<?php

namespace Phel;

use Phel\Exceptions\ReaderException;
use Phel\Lang\Boolean;
use Phel\Lang\Keyword;
use Phel\Lang\Nil;
use Phel\Lang\Number;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Stream\CharStream;
use Phel\Lang\PhelString;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Stream\CharData;
use Phel\Stream\SourceLocation;

class Reader {

    private $syntax = [
        '(', ')', '[', ']', '{', '}', '\'', ',', ';', '~'
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
                return $this->rddelim($stream, '"');
                
            case '@':
                if ($stream->peek()->getChar() == "[") {
                    $this->readStream($stream);
                    return $this->withSourceLocation($c, fn() => PhelArray::create(...$this->rdlist($stream, ']')));
                } else if ($stream->peek()->getChar() == "{") {
                    $this->readStream($stream);
                    $xs = $this->rdlist($stream, "}");
                    if ($xs instanceof Tuple && count($xs) % 2 === 0) {
                        $table = Table::fromKVs(...$this->rdlist($stream, "}"));
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
        if (is_numeric($word)) {
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
            } else if ($term == ']') {
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
        $cData = $this->readStream($stream);
        if ($cData === false) {
            throw new ReaderException('missing delimiter', $this->startLocation, $this->lastLocation, $this->readChars);
        } else if ($esc) {
            return $cData->getChar() . $this->rddelim($stream, $delimiter);
        } else if ($cData->getChar() == "\\") {
            return $this->rddelim($stream, $delimiter, true);
        } else if ($cData->getChar() == $delimiter) {
            return "";
        } else {
            return $cData->getChar() . $this->rddelim($stream, $delimiter);
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
}