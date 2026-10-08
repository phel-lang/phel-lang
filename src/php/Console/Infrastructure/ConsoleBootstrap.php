<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use InvalidArgumentException;
use Override;
use Phel\Console\Application\WarnDeprecationsFlag;
use Phel\Console\ConsoleFactory;
use Phel\Shared\EnvVar;
use Phel\Shared\InvocationError;
use Phel\Shared\NoColor;
use Phel\Shared\OptimizationLevel;
use Phel\Shared\Process\CpuCountDetector;
use Phel\Shared\ScalarCoercion;
use Phel\Shared\StandardError;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_slice;
use function array_values;
use function in_array;
use function str_starts_with;

/**
 * @method ConsoleFactory getFactory()
 *
 * @internal
 */
#[ServiceMap(method: 'getFactory', className: ConsoleFactory::class)]
final class ConsoleBootstrap extends Application
{
    use ServiceResolverAwareTrait;

    private ?string $resolvedVersion = null;

    /**
     * Resolved on first use: reading it costs two git processes, and only
     * `--version`, `list` and help print it.
     */
    #[Override]
    public function getVersion(): string
    {
        return $this->resolvedVersion ??= $this->getFactory()->createVersionResolver()->resolve();
    }

    /**
     * Sanitizes argv, strips deprecation flags, rewrites a bare --help/-h into the
     * `list` command, then runs the Symfony application with auto-exit disabled.
     *
     * Does not return: after the application finishes it clears the filesystem
     * cache and terminates via exit() with the resolved exit code, so any code
     * placed after the call site is unreachable.
     */
    #[Override]
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $this->setAutoExit(false);

        $sanitizedArgs = $this->getFactory()
            ->createArgvInputSanitizer($this->getDefinition())
            ->sanitize(ScalarCoercion::toStringList($_SERVER['argv'] ?? null));

        $strippedArgs = WarnDeprecationsFlag::strip($sanitizedArgs);
        if ($strippedArgs !== $sanitizedArgs) {
            $this->getFactory()->getCompilerFacade()->enableDeprecationWarnings();
        }

        $sanitizedArgs = $strippedArgs;

        $this->setDefaultCommand('repl');

        if ($this->isTopLevelHelp($sanitizedArgs)) {
            $sanitizedArgs = $this->replaceHelpWithList($sanitizedArgs);
        }

        if (!$input instanceof InputInterface) {
            $input = new ArgvInput($sanitizedArgs);
        }

        $exitCode = parent::run($input, $output);
        $this->getFactory()->getFilesystemFacade()->clearAll();

        exit($exitCode);
    }

    /**
     * A bad Phel environment switch would otherwise surface mid-compile as a
     * stack trace, after the command already started.
     */
    #[Override]
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            OptimizationLevel::resolve(null);
            EnvVar::flag(EnvVar::WARN_DEPRECATIONS);
            CpuCountDetector::fromEnv();
        } catch (InvalidArgumentException $invalidArgumentException) {
            return InvocationError::report($output, $invalidArgumentException->getMessage());
        }

        try {
            return parent::doRun($input, $output);
        } catch (CommandNotFoundException $commandNotFoundException) {
            return $this->reportInvocationError($commandNotFoundException, $output);
        }
    }

    /**
     * Binds the input before the command runs, so an unknown option or a
     * missing argument exits 2 instead of Symfony's 1. Once the command runs,
     * a console exception is the command's own failure. `help` is skipped:
     * it ignores the options it does not know, so `lint --nope --help` still
     * shows help.
     */
    #[Override]
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        if ($command instanceof HelpCommand) {
            return parent::doRunCommand($command, $input, $output);
        }

        try {
            $input->bind($this->definitionOf($command));
            $input->validate();
        } catch (ExceptionInterface $exception) {
            return $this->reportInvocationError($exception, $output);
        }

        return parent::doRunCommand($command, $input, $output);
    }

    /**
     * Phel's own coloured text (error reports, printed values) follows the
     * decision Symfony just made for stdout, where every command writes it.
     */
    #[Override]
    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        parent::configureIO($input, $output);

        NoColor::followOutput($output->isDecorated());
    }

    /**
     * What `Command::run()` binds: the application's arguments, then the
     * command's, and both sets of options, keyed by name so a definition that
     * already holds the application's entries does not repeat them.
     */
    private function definitionOf(Command $command): InputDefinition
    {
        $application = $this->getDefinition();
        $own = $command->getDefinition();

        return new InputDefinition([
            ...array_values([...$application->getArguments(), ...$own->getArguments()]),
            ...array_values([...$application->getOptions(), ...$own->getOptions()]),
        ]);
    }

    /**
     * Symfony's own rendering, usage line and "Did you mean" included, on stderr.
     */
    private function reportInvocationError(Throwable $exception, OutputInterface $output): int
    {
        $this->renderThrowable($exception, StandardError::of($output));

        return Command::INVALID;
    }

    /**
     * Detect when --help/-h is requested without an explicit command,
     * so we show top-level help listing all commands instead of repl help.
     *
     * @param list<string> $argv
     */
    private function isTopLevelHelp(array $argv): bool
    {
        $args = array_slice($argv, 1);

        $hasHelp = in_array('--help', $args, true) || in_array('-h', $args, true);
        if (!$hasHelp) {
            return false;
        }

        foreach ($args as $arg) {
            if ($arg === '--help') {
                continue;
            }

            if ($arg === '-h') {
                continue;
            }

            if (!str_starts_with((string) $arg, '-')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip --help/-h flags and insert 'list' as the command,
     * so Symfony Console executes the list command directly.
     *
     * @param list<string> $argv
     *
     * @return list<string>
     */
    private function replaceHelpWithList(array $argv): array
    {
        $filtered = array_values(array_filter(
            $argv,
            static fn(string $arg): bool => $arg !== '--help' && $arg !== '-h',
        ));

        array_splice($filtered, 1, 0, ['list']);

        return $filtered;
    }
}
