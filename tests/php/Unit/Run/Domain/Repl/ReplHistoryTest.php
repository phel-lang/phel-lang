<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Run\Domain\Repl\ReplHistory;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReplHistoryTest extends TestCase
{
    private const string CORE_NS = CompilerConstants::PHEL_CORE_NAMESPACE;

    protected function setUp(): void
    {
        Registry::getInstance()->clear();
    }

    public function test_register_initializes_symbols_in_analyzer_env_and_registry(): void
    {
        $env = new GlobalEnvironment();
        $history = new ReplHistory($env);

        $history->register();

        foreach ([ReplHistory::LAST_RESULT_1, ReplHistory::LAST_RESULT_2, ReplHistory::LAST_RESULT_3, ReplHistory::LAST_EXCEPTION] as $name) {
            self::assertTrue(
                $env->hasDefinition(self::CORE_NS, Symbol::create($name)),
                'Analyzer env must know about ' . $name,
            );
            self::assertNull(Registry::getInstance()->getDefinition(self::CORE_NS, $name));
        }
    }

    public function test_record_result_shifts_history(): void
    {
        $history = new ReplHistory(new GlobalEnvironment());
        $history->register();

        $history->recordResult(1);
        $history->recordResult(2);
        $history->recordResult(3);

        self::assertSame(3, Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_1));
        self::assertSame(2, Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_2));
        self::assertSame(1, Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_3));
    }

    public function test_record_result_drops_oldest_after_four_evaluations(): void
    {
        $history = new ReplHistory(new GlobalEnvironment());
        $history->register();

        $history->recordResult('a');
        $history->recordResult('b');
        $history->recordResult('c');
        $history->recordResult('d');

        self::assertSame('d', Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_1));
        self::assertSame('c', Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_2));
        self::assertSame('b', Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_3));
    }

    public function test_record_result_preserves_null_value(): void
    {
        $history = new ReplHistory(new GlobalEnvironment());
        $history->register();

        $history->recordResult(42);
        $history->recordResult(null);

        self::assertNull($history->lastResult());
        self::assertNull(Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_1));
        self::assertSame(42, Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_2));
    }

    public function test_record_exception_stores_in_registry_and_accessor(): void
    {
        $history = new ReplHistory(new GlobalEnvironment());
        $history->register();

        $exception = new RuntimeException('boom');

        $history->recordException($exception);

        self::assertSame($exception, $history->lastException());
        self::assertSame(
            $exception,
            Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_EXCEPTION),
        );
    }

    public function test_record_exception_does_not_shift_result_history(): void
    {
        $history = new ReplHistory(new GlobalEnvironment());
        $history->register();
        $history->recordResult('keep');

        $history->recordException(new RuntimeException('boom'));

        self::assertSame('keep', Registry::getInstance()->getDefinition(self::CORE_NS, ReplHistory::LAST_RESULT_1));
    }
}
