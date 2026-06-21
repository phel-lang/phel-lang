<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Run\Infrastructure\Command\AgentInstallCommand;
use Phel\Run\Infrastructure\Command\CompileCommand;
use Phel\Run\Infrastructure\Command\ConfigCommand;
use Phel\Run\Infrastructure\Command\DoctorCommand;
use Phel\Run\Infrastructure\Command\EvalCommand;
use Phel\Run\Infrastructure\Command\InitCommand;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;
use Phel\Run\Infrastructure\Command\TestWorkerCommand;
use Symfony\Component\Console\Command\LazyCommand;

final class RunCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('init', [], 'Initialize a new Phel project with minimal configuration', false, static fn(): InitCommand => new InitCommand()),
            new LazyCommand('agent-install', [], 'Install agent skill files (Claude, Cursor, Codex, Gemini, Copilot, Aider) into the current project', false, static fn(): AgentInstallCommand => new AgentInstallCommand()),
            new LazyCommand('ns', ['loaded-ns'], 'Display all loaded namespaces or inspect a namespace', false, static fn(): NsCommand => new NsCommand()),
            new LazyCommand('repl', [], 'Start a Repl', false, static fn(): ReplCommand => new ReplCommand()),
            new LazyCommand('eval', ['e'], 'Evaluate a Phel expression and print the result', false, static fn(): EvalCommand => new EvalCommand()),
            new LazyCommand('compile', [], 'Compile a Phel snippet and print the emitted PHP. Does not evaluate.', false, static fn(): CompileCommand => new CompileCommand()),
            new LazyCommand('run', ['r'], 'Runs a script', false, static fn(): RunCommand => new RunCommand()),
            new LazyCommand(TestCommand::COMMAND_NAME, ['t'], 'Tests the given files. If no filenames are provided all tests in the "tests" directory are executed', false, static fn(): TestCommand => new TestCommand()),
            new LazyCommand(TestWorkerCommand::COMMAND_NAME, [], 'Internal: parallel test worker. Not for direct use.', true, static fn(): TestWorkerCommand => new TestWorkerCommand()),
            new LazyCommand('doctor', [], 'Check system requirements for running the Phel CLI', false, static fn(): DoctorCommand => new DoctorCommand()),
            new LazyCommand('config', [], 'Show the effective Phel configuration and where it comes from', false, static fn(): ConfigCommand => new ConfigCommand()),
        ];
    }
}
