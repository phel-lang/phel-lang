<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Phel\Mutate\Domain\MutateOptions;
use Phel\Mutate\Domain\MutationPlan;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\NamespaceInformation;
use Phel\Shared\Process\GitChangedFiles;
use Phel\Shared\Process\GitUnavailableException;
use Phel\Shared\ScalarCoercion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;

use function array_filter;
use function array_keys;
use function array_values;
use function getcwd;
use function is_array;
use function is_dir;
use function is_file;
use function realpath;
use function sort;
use function str_contains;
use function str_starts_with;

/**
 * Turns the options into what the run needs: which `.phel` files to
 * mutate, which files the worker loads (in dependency order), and which
 * namespaces hold the tests to run against every mutant.
 *
 * @internal
 */
final readonly class MutationPlanner
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private CommandFacadeInterface $commandFacade,
        private GitChangedFiles $changedFiles,
    ) {}

    /**
     * @throws GitUnavailableException with `changed`, outside a git repository
     */
    public function plan(MutateOptions $options): MutationPlan
    {
        $sourceFiles = $this->collectPhelFiles(
            $options->paths === [] ? $this->commandFacade->getProjectSourceDirectories() : $options->paths,
        );
        if ($options->changed) {
            $sourceFiles = $this->onlyChanged($sourceFiles, $options->changedRef);
        }

        // Test namespaces first (with their whole dependency closure, which
        // usually already contains the sources), then whatever mutated file
        // no test requires, so every mutated namespace exists in the worker.
        $infos = $this->runFacade->getDependenciesFromPaths($this->collectPhelFiles($options->testPaths));
        $known = [];
        foreach ($infos as $info) {
            $known[$info->getFile()] = true;
        }

        foreach ($this->runFacade->getDependenciesFromPaths($sourceFiles) as $info) {
            if (!isset($known[$info->getFile()])) {
                $infos[] = $info;
                $known[$info->getFile()] = true;
            }
        }

        $loadOrder = [];
        foreach ($infos as $info) {
            if (!$this->isPhpUnitFixture($info)) {
                $loadOrder[] = $info->getFile();
            }
        }

        return new MutationPlan($sourceFiles, $loadOrder, $this->testNamespaces($options, $infos));
    }

    /**
     * @param list<NamespaceInformation> $infos
     *
     * @return list<string>
     */
    private function testNamespaces(MutateOptions $options, array $infos): array
    {
        if ($options->testPaths !== []) {
            $namespaces = [];
            foreach ($this->collectPhelFiles($options->testPaths) as $file) {
                $namespaces[] = $this->runFacade->getNamespaceFromFile($file)->getNamespace();
            }

            return $namespaces;
        }

        $testRoots = [];
        foreach ($this->commandFacade->getTestDirectories() as $dir) {
            $real = realpath($dir);
            $testRoots[] = $real === false ? $dir : $real;
        }

        $namespaces = [];
        foreach ($infos as $info) {
            foreach ($testRoots as $root) {
                if (str_starts_with($info->getFile(), $root . '/')) {
                    $namespaces[] = $info->getNamespace();
                    break;
                }
            }
        }

        return $namespaces;
    }

    /**
     * `--changed`: the source files git reports as changed, so only the
     * definitions someone touched are mutated. File-level on purpose: a
     * changed line invalidates what its whole definition does, and the
     * definition is the unit a mutant redefines anyway.
     *
     * @param list<string> $sourceFiles
     *
     * @return list<string>
     */
    private function onlyChanged(array $sourceFiles, ?string $ref): array
    {
        $changed = [];
        foreach ($this->changedFiles->changedFiles($ref, getcwd() ?: '.') as $file) {
            $real = realpath($file);
            $changed[$real === false ? $file : $real] = true;
        }

        return array_values(array_filter($sourceFiles, static fn(string $file): bool => isset($changed[$file])));
    }

    /**
     * Files and directories (walked recursively) to the `.phel` files they hold.
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function collectPhelFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $real = realpath($path);
            if ($real === false) {
                continue;
            }

            if (is_file($real)) {
                $files[$real] = true;
                continue;
            }

            if (!is_dir($real)) {
                continue;
            }

            try {
                $iterator = new RegexIterator(
                    new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS)),
                    '/^.+\.phel$/i',
                    RegexIterator::GET_MATCH,
                );
                foreach ($iterator as $match) {
                    if (is_array($match) && isset($match[0])) {
                        $files[ScalarCoercion::toString($match[0])] = true;
                    }
                }
            } catch (UnexpectedValueException) {
                continue;
            }
        }

        $list = array_keys($files);
        sort($list);

        return $list;
    }

    /**
     * The repository's PHPUnit fixtures are Phel files too; evaluating them
     * as project namespaces is wrong, exactly as `phel test` skips them.
     */
    private function isPhpUnitFixture(NamespaceInformation $info): bool
    {
        return str_contains($info->getFile(), 'tests/php/Integration/')
            || str_contains($info->getFile(), 'tests/php/Benchmark/');
    }
}
