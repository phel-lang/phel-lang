<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Run\Domain\StdinReaderInterface;
use Phel\Run\RunFacade;
use Phel\Run\RunFactory;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function sprintf;

/**
 * `phel compile` — read a Phel snippet and print the emitted PHP.
 *
 * @method RunFacade   getFacade()
 * @method RunFactory  getFactory()
 */
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
#[ServiceMap(method: 'getFactory', className: RunFactory::class)]
final class CompileCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string STDIN_MARKER = '-';

    private const string TARGET_PHP = 'php';

    /** @var list<string> */
    private const array SUPPORTED_TARGETS = [self::TARGET_PHP];

    public function __construct(
        private ?StdinReaderInterface $stdinReader = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('compile')
            ->setDescription('Compile a Phel snippet and print the emitted PHP. Does not evaluate.')
            ->setHelp(<<<'HELP'
Prints the PHP a snippet/file/stdin compiles to, without running it.

A pure top-level value (e.g. <comment>(+ 1 2)</comment> folds to <comment>3</comment>) is discarded in
statement context and emits no PHP; in that case a note on stderr
reports the value the snippet reduces to, and stdout stays empty.

<info>Examples:</info>
  <comment>phel compile '(+ 1 2)'</comment>      Compile an inline expression
  <comment>echo '(println "hi")' | phel compile -</comment>   Compile from stdin
HELP)
            ->addArgument(
                'source',
                InputArgument::OPTIONAL,
                'Phel expression, path to a `.phel` file, or "-" to read from stdin.',
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'Compilation target. Currently only "php" is supported.',
                self::TARGET_PHP,
                self::SUPPORTED_TARGETS,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $target = ScalarCoercion::toString($input->getOption('target'));
        if (!in_array($target, self::SUPPORTED_TARGETS, true)) {
            $stderr->writeln(
                sprintf('Unsupported target "%s". Supported: %s', $target, implode(', ', self::SUPPORTED_TARGETS)),
            );
            return self::FAILURE;
        }

        $this->getFacade()->loadPhelNamespaces();

        $source = $this->resolveSource(ScalarCoercion::toString($input->getArgument('source') ?? null));

        $ok = $this->getFactory()
            ->createCompileExecutor()
            ->execute(
                $source,
                static fn(string $chunk) => $output->write($chunk),
                static fn(string $chunk) => $stderr->write($chunk),
            );

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The positional argument can be an inline expression, the literal
     * "-" for stdin, or a path to a `.phel` file. File path is
     * preferred when the argument exactly matches an existing file so
     * users can run `phel compile snippet.phel` without quoting.
     */
    private function resolveSource(string $arg): string
    {
        if ($arg === self::STDIN_MARKER) {
            return ($this->stdinReader ?? $this->getFactory()->createStdinReader())->read();
        }

        if ($arg !== '' && is_file($arg)) {
            $contents = file_get_contents($arg);
            return $contents === false ? '' : $contents;
        }

        return $arg;
    }
}
