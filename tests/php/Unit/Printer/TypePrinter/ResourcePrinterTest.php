<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Shared\Printer\TypePrinter\ResourcePrinter;
use PHPUnit\Framework\TestCase;

final class ResourcePrinterTest extends TestCase
{
    public function test_print(): void
    {
        $resource = tmpfile();
        self::assertIsResource($resource);
        try {
            self::assertSame('#<PHP Resource id #' . get_resource_id($resource) . '>', new ResourcePrinter()->print($resource));
        } finally {
            fclose($resource);
        }
    }
}
