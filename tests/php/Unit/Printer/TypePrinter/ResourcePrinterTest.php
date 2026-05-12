<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Shared\Printer\TypePrinter\ResourcePrinter;
use PHPUnit\Framework\TestCase;

final class ResourcePrinterTest extends TestCase
{
    public function test_print(): void
    {
        self::assertMatchesRegularExpression(
            '<PHP Resource id #Resource id #\d+>',
            new ResourcePrinter()->print($this->getResource()),
        );
    }

    /**
     * @return resource
     */
    private function getResource()
    {
        return tmpfile();
    }
}
