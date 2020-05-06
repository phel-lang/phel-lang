<?php 

namespace Phel\Token;

use Phel\Lang\SourceLocationTrait;
use Phel\Stream\SourceLocation;

abstract class Token {

    use SourceLocationTrait;

    /**
     * @var string
     */
    private $code;

    public function __construct(string $code, SourceLocation $startLocation, SourceLocation $endLocation)
    {
        $this->code = $code;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }

    public function getCode(): string {
        return $this->code;
    }
}