<?php 

namespace Phel\Stream;

class StringCharStream implements CharStream {

    protected $currentIndex = 0;

    protected $totalLength = 0;

    protected $chars = [];

    protected $lineNumber = 1;

    protected $lineOffset = 1;

    public function __construct(string $s)
    {
        $this->chars = preg_split('//u', $s, null, PREG_SPLIT_NO_EMPTY);
        $this->totalLength = count($this->chars);
    }

    /**
     * Returns the character that is waiting to be read
     * from the stream
     * 
     * @return CharData|false;
     */
    public function peek() {
        if ($this->currentIndex < $this->totalLength) {
            return new CharData(
                $this->chars[$this->currentIndex],
                new SourceLocation(
                    'string',
                    $this->lineNumber,
                    $this->lineOffset
                )
            );
        } else {
            return false;
        }
    }

    /**
     * Reads the next character from the stream
     * 
     * @return string
     */
    public function read() {
        if ($this->currentIndex < $this->totalLength) {
            $char = $this->chars[$this->currentIndex];
            $result = new CharData(
                $char,
                new SourceLocation(
                    'string',
                    $this->lineNumber,
                    $this->lineOffset
                )
            );
            $this->currentIndex++;

            if ($char == "\n") {
                $this->lineNumber++;
                $this->lineOffset = 1;
            } else {
                $this->lineOffset++;
            }

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Returns the current line number
     * 
     * @return int
     */
    public function getLineNumber() {
        return $this->lineNumber;
    }

    /**
     * Returns the current offset in the line
     * 
     * @return int
     */
    public function getOffset() {
        return $this->currentIndex;
    }
}