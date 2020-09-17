<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Compiler\EvalCompiler;
use Phel\Runtime;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

final class TestCommand
{
    public const NAME = 'test';

    private ?Runtime $runtime;

    public function __construct(?Runtime $runtime = null)
    {
        $this->runtime = $runtime;
    }

    public function run(string $currentDirectory, array $paths): bool
    {
        $namespaces = count($paths) === 0
            ? $this->getNamespacesFromConfig($currentDirectory)
            : array_map(fn ($filename) => CommandUtils::getNamespaceFromFile($filename), $paths);

        if (empty($namespaces)) {
            throw new \RuntimeException('Can not find any tests');
        }

        $rt = $this->initializeRuntime($currentDirectory);
        $compiler = new EvalCompiler($rt->getEnv());
        $nsString = implode(' ', array_map(fn (string $x) => "'" . $x, $namespaces));

        return $compiler->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
    }

    private function getNamespacesFromConfig(string $currentDirectory): array
    {
        $config = CommandUtils::getPhelConfig($currentDirectory);
        $namespaces = [];

        if (isset($config['tests'])) {
            foreach ($config['tests'] as $testDir) {
                $namespaces = array_merge($namespaces, $this->findAllNs($currentDirectory . $testDir));
            }
        }

        return $namespaces;
    }

    private function initializeRuntime(string $currentDirectory): Runtime
    {
        $rt = $this->runtime ?? CommandUtils::loadRuntime($currentDirectory);
        $rt->loadNs('phel\test');

        return $rt;
    }

    private function findAllNs(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RecursiveRegexIterator::GET_MATCH);

        return array_map(
            fn ($file) => CommandUtils::getNamespaceFromFile($file[0]),
            iterator_to_array($phelIterator)
        );
    }
}
