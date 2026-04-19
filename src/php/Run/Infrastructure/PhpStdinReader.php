<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure;

use Phel\Run\Domain\StdinReaderInterface;

use function defined;
use function fopen;
use function stream_get_contents;

final class PhpStdinReader implements StdinReaderInterface
{
    /**
     * @param resource|null $stream
     */
    public function __construct(private $stream = null) {}

    public function read(): string
    {
        $stream = $this->stream ?? (defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
        if ($stream === false) {
            return '';
        }

        $contents = stream_get_contents($stream);

        return $contents === false ? '' : $contents;
    }
}
