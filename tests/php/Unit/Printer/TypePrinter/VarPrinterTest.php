<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\PhelVar;
use Phel\Printer\TypePrinter\VarPrinter;
use PHPUnit\Framework\TestCase;

final class VarPrinterTest extends TestCase
{
    public function test_prints_with_var_quote_syntax(): void
    {
        $ref = new PhelVar('user', 'my-var');

        self::assertSame("#'user/my-var", new VarPrinter()->print($ref));
    }

    public function test_prints_namespace_with_dot_separator(): void
    {
        $ref = new PhelVar('phel.core', 'map');

        self::assertSame("#'phel.core/map", new VarPrinter()->print($ref));
    }
}
