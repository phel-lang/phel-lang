<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Api\Infrastructure\Command\AnalyzeCommand;
use Phel\Api\Infrastructure\Command\ApiDaemonCommand;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Api\Infrastructure\Command\IndexCommand;
use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Symfony\Component\Console\Command\LazyCommand;

final class ApiCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('doc', [], 'Display the docs for any/all phel functions', false, static fn(): DocCommand => new DocCommand()),
            new LazyCommand('analyze', [], 'Run semantic analysis on a single Phel source file and emit JSON diagnostics', false, static fn(): AnalyzeCommand => new AnalyzeCommand()),
            new LazyCommand('index', [], 'Build a project-level symbol index across one or more source directories', false, static fn(): IndexCommand => new IndexCommand()),
            new LazyCommand('api-daemon', [], 'Long-running JSON-RPC daemon exposing the Api semantic analysis facade over newline-delimited JSON (stdio).', false, static fn(): ApiDaemonCommand => new ApiDaemonCommand()),
        ];
    }
}
