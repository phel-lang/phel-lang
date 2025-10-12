<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Domain\Exceptions\Extractor\SourceMapExtractorInterface;
use RuntimeException;

use function fclose;
use function fgets;
use function fopen;
use function sprintf;

final class SourceMapExtractor implements SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation
    {
        $handle = fopen($filename, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open file "%s".', $filename));
        }

        $filenameComment = fgets($handle);
        $sourceMapComment = fgets($handle);

        fclose($handle);

        return new SourceMapInformation(
            $filenameComment === false ? '' : $filenameComment,
            $sourceMapComment === false ? '' : $sourceMapComment,
        );
    }
}
