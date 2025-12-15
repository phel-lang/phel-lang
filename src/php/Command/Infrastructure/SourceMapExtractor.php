<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Domain\Exceptions\Extractor\SourceMapExtractorInterface;

use function fclose;
use function fgets;
use function fopen;

final class SourceMapExtractor implements SourceMapExtractorInterface
{
    public function extractFromFile(string $filename): SourceMapInformation
    {
        if (!file_exists($filename)) {
            return new SourceMapInformation('', '');
        }

        $handle = fopen($filename, 'rb');

        if ($handle === false) {
            return new SourceMapInformation('', '');
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
