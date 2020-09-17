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
        if (count($paths) === 0) {
            $namespaces = $this->getNamespacesFromConfig($currentDirectory);
        } else {
            $namespaces = [];
            foreach ($paths as $filename) {
                $namespaces[] = CommandUtils::getNamespaceFromFile($filename);
            }
        }

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
        if ($this->runtime === null) {
            $rt = CommandUtils::loadRuntime($currentDirectory);
        } else {
            $rt = $this->runtime;
        }
        $rt->loadNs('phel\test');

        return $rt;
    }

    private function findAllNs(string $directory): array
    {
        $dirIterator = new RecursiveDirectoryIterator($directory);
        $iterator = new RecursiveIteratorIterator($dirIterator);
        $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RecursiveRegexIterator::GET_MATCH);

        $namespaces = [];
        foreach ($phelIterator as $file) {
            $file = $file[0];
            $namespaces[] = CommandUtils::getNamespaceFromFile($file);
        }

        return $namespaces;
    }
}
