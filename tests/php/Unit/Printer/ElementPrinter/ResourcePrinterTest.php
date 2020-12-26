<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\ElementPrinter;

use Phel\Printer\ElementPrinter\ResourcePrinter;
use PHPUnit\Framework\TestCase;

final class ResourcePrinterTest extends TestCase
{
    public function testPrint(): void
    {
        self::assertMatchesRegularExpression(
            '<PHP Resource id #Resource id #\d+>',
            (new ResourcePrinter())->print($this->getResource())
        );
    }

    /**
     * @return resource
     */
    private function getResource()
    {
        $resource = curl_init('');
        curl_close($resource);

        return $resource;
    }
}
