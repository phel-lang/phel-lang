<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use Throwable;

final class ReplHistory
{
    public const string LAST_RESULT_1 = '*1';

    public const string LAST_RESULT_2 = '*2';

    public const string LAST_RESULT_3 = '*3';

    public const string LAST_EXCEPTION = '*e';

    private const array NAMES = [
        self::LAST_RESULT_1,
        self::LAST_RESULT_2,
        self::LAST_RESULT_3,
        self::LAST_EXCEPTION,
    ];

    private mixed $result1 = null;

    private mixed $result2 = null;

    private mixed $result3 = null;

    private ?Throwable $lastException = null;

    public function __construct(
        private readonly GlobalEnvironmentInterface $globalEnvironment,
    ) {}

    /**
     * Register the history symbols so the analyzer can resolve them in REPL input.
     */
    public function register(): void
    {
        $ns = CompilerConstants::PHEL_CORE_NAMESPACE;

        foreach (self::NAMES as $name) {
            $this->globalEnvironment->addDefinition($ns, Symbol::create($name));
            Phel::addDefinition($ns, $name, null);
        }
    }

    public function recordResult(mixed $value): void
    {
        $this->result3 = $this->result2;
        $this->result2 = $this->result1;
        $this->result1 = $value;

        $ns = CompilerConstants::PHEL_CORE_NAMESPACE;
        Phel::addDefinition($ns, self::LAST_RESULT_1, $this->result1);
        Phel::addDefinition($ns, self::LAST_RESULT_2, $this->result2);
        Phel::addDefinition($ns, self::LAST_RESULT_3, $this->result3);
    }

    public function recordException(Throwable $exception): void
    {
        $this->lastException = $exception;
        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, self::LAST_EXCEPTION, $exception);
    }

    public function lastResult(): mixed
    {
        return $this->result1;
    }

    public function lastException(): ?Throwable
    {
        return $this->lastException;
    }
}
