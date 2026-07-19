<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Compile\SymbolMetaStripper;
use PHPUnit\Framework\TestCase;

final class SymbolMetaStripperTest extends TestCase
{
    public function test_strips_location_meta_argument(): void
    {
        $code = <<<'PHP'
\Phel::addDefinition(
  "user",
  "x",
  1,
  \Phel::locationMeta(
    \Phel::location("string", 1, 0),
    \Phel::location("string", 1, 10)
  )
);
PHP;
        $stripped = SymbolMetaStripper::strip($code);

        self::assertStringNotContainsString('locationMeta', $stripped);
        self::assertStringContainsString('"user"', $stripped);
        self::assertSame(substr_count($stripped, '('), substr_count($stripped, ')'));
    }

    public function test_docstring_parens_do_not_break_stripping(): void
    {
        $code = <<<'PHP'
\Phel::addDefinition(
  "user",
  "f",
  new class() extends \Phel\Lang\AbstractFn {
    public function __invoke($x) {
      return $x;
    }
  },
  \Phel::locationMeta(
    \Phel::location("string", 2, 0),
    \Phel::location("string", 2, 20),
    \Phel\Lang\Keyword::create("doc"), "```phel\n(f x) ; => (identity (x))\n```\n",
    "min-arity", 1
  )
);
PHP;
        $stripped = SymbolMetaStripper::strip($code);

        self::assertStringNotContainsString('locationMeta', $stripped);
        self::assertStringNotContainsString('min-arity', $stripped);
        self::assertStringContainsString('__invoke', $stripped);
    }

    public function test_definitions_without_meta_pass_through_unchanged(): void
    {
        $code = <<<'PHP'
\Phel::addDefinition(
  "user",
  "y",
  2
);
PHP;
        self::assertSame($code, SymbolMetaStripper::strip($code));
    }

    public function test_strips_every_occurrence(): void
    {
        $one = <<<'PHP'
\Phel::addDefinition(
  "user",
  "a",
  1,
  \Phel::locationMeta(\Phel::location("s", 1, 0), \Phel::location("s", 1, 5))
);

PHP;
        $code = $one . $one;

        $stripped = SymbolMetaStripper::strip($code);

        self::assertStringNotContainsString('locationMeta', $stripped);
        self::assertSame(2, substr_count($stripped, 'addDefinition'));
    }
}
