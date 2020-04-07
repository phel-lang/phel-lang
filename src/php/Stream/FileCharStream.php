<?php 

namespace Phel\Stream;

class FileCharStream implements CharStream {

    protected $filename;

    protected $isOpen = false;

    protected $resource;

    protected $isEof = false;

    protected $currentIndex = 0;

    protected $totalLength = 0;

    protected $chars = [];

    protected $lineNumber = 0;

    public function __construct($filename)
    {
        $this->filename = realpath($filename);
    }

    private function open() {
        if ($this->isOpen === false) {
            $this->resource = fopen($this->filename, 'r');
            $this->isOpen = true;
            $this->readNextLine();
        }
    }

    private function readNextLine() {
        $line = fgets($this->resource);
        $this->lineNumber++;
        if ($line === false) {
            $this->isEof = true;
        } else {
            $this->chars = preg_split('//u', $line, null, PREG_SPLIT_NO_EMPTY);
            $this->currentIndex = 0;
            $this->totalLength = count($this->chars);
        }
    }

    /**
     * Returns the character that is waiting to be read
     * from the stream
     * 
     * @return CharData|false;
     */
    public function peek() {
        $this->open();

        if ($this->isEof) {
            return false;
        } else {
            return new CharData(
                $this->chars[$this->currentIndex],
                new SourceLocation(
                    $this->filename,
                    $this->lineNumber,
                    $this->currentIndex + 1
                )
            );
        }
    }

    /**
     * Reads the next character from the stream
     * 
     * @return string
     */
    public function read() {
        $this->open();

        if ($this->isEof) {
            return false;
        } else {
            $result = new CharData(
                $this->chars[$this->currentIndex],
                new SourceLocation(
                    $this->filename,
                    $this->lineNumber,
                    $this->currentIndex + 1
                )
            );
            $this->currentIndex++;

            if ($this->currentIndex >= count($this->chars)) {
                $this->readNextLine();
            }

            return $result;
        }
    }
}