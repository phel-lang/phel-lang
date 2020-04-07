<?php

namespace Phel\Exceptions;

use Exception;
use Phel\Stream\SourceLocation;

class ReaderException extends Exception {

    /**
     * @var SourceLocation
     */
    private $startLocation;

    /**
     * @var SourceLocation
     */
    private $endLocation;

    /**
     * @var string
     */
    private $phelCode;

    public function __construct($message, $startLocation, $endLocation, $phelCode)
    {
        parent::__construct($message);
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->phelCode = $phelCode;
    }

    public function getStartLocation() {
        return $this->startLocation;
    }

    public function getEndLocation() {
        return $this->endLocation;
    }

    public function getPhelCode() {
        return $this->phelCode;
    }

    public function __toString()
    {
        $firstLine = $this->startLocation->getLine();

        echo $this->getMessage() . "\n";
        echo "in " . $this->getStartLocation()->getFile() . ':' . $firstLine . "\n\n";

        $lines = explode("\n", $this->phelCode);
        $padLength = strlen((string) $this->endLocation->getLine()) - strlen((string) $firstLine);
        foreach ($lines as $index => $line) {
            echo str_pad($firstLine + $index, $padLength, ' ', STR_PAD_LEFT);
            echo "| ";
            echo $line;
            echo "\n";
        }
    }

}