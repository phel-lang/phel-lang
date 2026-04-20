<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Phel\Lang\VarReference;
use Phel\Printer\TypePrinter\VarReferencePrinter;
use PHPUnit\Framework\TestCase;

final class VarReferencePrinterTest extends TestCase
{
    public function test_prints_with_clojure_like_var_syntax(): void
    {
        $ref = new VarReference('user', 'my-var');

        self::assertSame("#'user/my-var", new VarReferencePrinter()->print($ref));
    }

    public function test_prints_namespace_with_backslashes(): void
    {
        $ref = new VarReference('phel\\core', 'map');

        self::assertSame("#'phel\\core/map", new VarReferencePrinter()->print($ref));
    }
}
