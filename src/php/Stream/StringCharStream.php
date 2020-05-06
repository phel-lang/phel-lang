<?php 

namespace Phel\Stream;

class StringCharStream implements CharStream {

    protected $currentIndex = 0;

    protected $totalLength = 0;

    protected $charData = [];

    public function __construct(string $s)
    {
        $chars = preg_split('//u', $s, null, PREG_SPLIT_NO_EMPTY);
        $this->charData = $this->buildCharData($chars);
        $this->totalLength = count($chars);
    }

    private function buildCharData($chars) {
        $charData = [];
        $lineNumber = 1;
        $lineOffset = 1;
        foreach ($chars as $char) {
            $charData[] = new CharData(
                $char,
                new SourceLocation(
                    'string',
                    $lineNumber,
                    $lineOffset
                )
            );

            if ($char == "\n") {
                $lineNumber++;
                $lineOffset = 1;
            } else {
                $lineOffset++;
            }
        }

        return $charData;
    }

    /**
     * Returns the character that is waiting to be read
     * from the stream
     * 
     * @return CharData|false;
     */
    public function peek() {
        if ($this->currentIndex < $this->totalLength) {
            return $this->charData[$this->currentIndex];
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
            $this->currentIndex++;

            return $this->charData[$this->currentIndex - 1];
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
        return $this->charData[$this->currentIndex]->getLineNumber();
    }

    /**
     * Returns the current offset in the line
     * 
     * @return int
     */
    public function getOffset() {
        return $this->charData[$this->currentIndex]->getOffset();
    }
}