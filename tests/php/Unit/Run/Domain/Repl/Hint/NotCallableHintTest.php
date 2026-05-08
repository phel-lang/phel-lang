<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl\Hint;

use Error;
use Phel\Run\Domain\Repl\Hint\NotCallableHint;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotCallableHintTest extends TestCase
{
    public function test_does_not_apply_when_message_unrelated(): void
    {
        $hint = new NotCallableHint();

        self::assertFalse($hint->appliesTo(new RuntimeException('something broke')));
    }

    public function test_applies_to_not_callable_message(): void
    {
        $hint = new NotCallableHint();
        $e = new Error('Object of type Phel\\Lang\\Collections\\LazySeq\\ChunkedSeq is not callable');

        self::assertTrue($hint->appliesTo($e));
        self::assertStringContainsString("'sequence'", $hint->hint($e));
        self::assertStringContainsString('first', $hint->hint($e));
    }

    public function test_maps_vector_type(): void
    {
        $hint = new NotCallableHint();
        $e = new Error('Object of type Phel\\Lang\\Collections\\Vector\\PersistentVector is not callable');

        self::assertStringContainsString("'vector'", $hint->hint($e));
    }

    public function test_falls_back_to_fqcn_for_unknown_phel_type(): void
    {
        $hint = new NotCallableHint();
        $e = new Error('Object of type App\\Custom\\Thing is not callable');

        self::assertStringContainsString("'App\\Custom\\Thing'", $hint->hint($e));
    }
}
