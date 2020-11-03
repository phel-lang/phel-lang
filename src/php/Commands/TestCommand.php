<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\Commands\Utils\NamespaceExtractorInterface;
use Phel\Compiler\EvalCompiler;
use Phel\RuntimeInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

final class TestCommand
{
    public const NAME = 'test';

    private RuntimeInterface $runtime;
    private NamespaceExtractorInterface $namespaceExtractor;

    public function __construct(
        RuntimeInterface $runtime,
        NamespaceExtractorInterface $namespaceExtractor
    ) {
        $this->runtime = $runtime;
        $this->namespaceExtractor = $namespaceExtractor;
    }

    public function run(string $currentDirectory, array $paths): bool
    {
        $namespaces = empty($paths)
            ? $this->getNamespacesFromConfig($currentDirectory)
            : array_map(fn ($filename) => $this->namespaceExtractor->getNamespaceFromFile($filename), $paths);

        if (empty($namespaces)) {
            throw new \RuntimeException('Can not find any tests');
        }

        $this->runtime->loadNs('phel\test');

        $compiler = new EvalCompiler($this->runtime->getEnv());
        $nsString = implode(' ', array_map(fn (string $x) => "'" . $x, $namespaces));

        return $compiler->eval('(do (phel\test/run-tests ' . $nsString . ') (successful?))');
    }

    private function getNamespacesFromConfig(string $currentDirectory): array
    {
        $config = static::getPhelConfig($currentDirectory);
        $namespaces = [];

        $testDirectories = $config['tests'] ?? [];
        foreach ($testDirectories as $testDir) {
            $allNamespacesInDir = $this->findAllNs($currentDirectory . $testDir);
            $namespaces = array_merge($namespaces, $allNamespacesInDir);
        }

        return $namespaces;
    }

    public static function getPhelConfig(string $currentDirectory): array
    {
        $composerContent = file_get_contents($currentDirectory . 'composer.json');
        if (!$composerContent) {
            throw new \Exception('Can not read composer.json in: ' . $currentDirectory);
        }

        $composerData = json_decode($composerContent, true);
        if (!$composerData) {
            throw new \Exception('Can not parse composer.json in: ' . $currentDirectory);
        }

        if (isset($composerData['extra']['phel'])) {
            return $composerData['extra']['phel'];
        }

        throw new \Exception('No Phel configuration found in composer.json');
    }

    private function findAllNs(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RecursiveRegexIterator::GET_MATCH);

        return array_map(
            fn ($file) => $this->namespaceExtractor->getNamespaceFromFile($file[0]),
            iterator_to_array($phelIterator)
        );
    }
}
