<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phar;
use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Shared\Facade\RunFacadeInterface;
use RuntimeException;

use function dirname;
use function getcwd;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function uniqid;
use function unlink;

final readonly class PhelFunctionRuntimeLoader
{
    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {}

    /**
     * @param list<string> $namespaces
     */
    public function load(array $namespaces): void
    {
        $previousEnv = GlobalEnvironmentSingleton::isInitialized()
            ? GlobalEnvironmentSingleton::getInstance()
            : null;
        $previousRegistry = $previousEnv !== null
            ? Registry::getInstance()->snapshot()
            : null;
        $previousNs = $previousEnv?->getNs();

        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        [$phelFile, $tempDir] = $this->writeDocumentSource($namespaces);

        try {
            $namespace = $this->runFacade
                ->getNamespaceFromFile($phelFile)
                ->getNamespace();

            $namespaceInformation = $this->runFacade->getDependenciesForNamespace(
                [
                    dirname($phelFile),
                    ...$this->runFacade->getAllPhelDirectories(),
                ],
                [$namespace, 'phel\\core'],
            );

            foreach ($namespaceInformation as $info) {
                $this->runFacade->evalFile($info);
            }
        } finally {
            unlink($phelFile);

            if ($tempDir !== null && is_dir($tempDir)) {
                rmdir($tempDir);
            }

            if ($previousEnv !== null && $previousRegistry !== null) {
                $this->mergePreviousState($previousEnv, $previousRegistry, $previousNs);
            }
        }
    }

    /**
     * Merges the snapshot taken before the destructive load back on top of
     * the loaded state so that callers (REPL, nREPL) keep their session
     * intact while still seeing the freshly loaded documentation namespaces.
     *
     * @param array{definitions: array<string, array<string, mixed>>, definitionsMetaData: array<string, array<string, mixed>>} $previousRegistry
     */
    private function mergePreviousState(
        GlobalEnvironmentInterface $previousEnv,
        array $previousRegistry,
        ?string $previousNs,
    ): void {
        $registry = Registry::getInstance();
        $current = $registry->snapshot();

        $mergedDefinitions = $current['definitions'];
        foreach ($previousRegistry['definitions'] as $ns => $entries) {
            $mergedDefinitions[$ns] = ($mergedDefinitions[$ns] ?? []) + $entries;
        }

        $mergedMeta = $current['definitionsMetaData'];
        foreach ($previousRegistry['definitionsMetaData'] as $ns => $entries) {
            $mergedMeta[$ns] = ($mergedMeta[$ns] ?? []) + $entries;
        }

        $registry->restore([
            'definitions' => $mergedDefinitions,
            'definitionsMetaData' => $mergedMeta,
        ]);

        GlobalEnvironmentSingleton::setInstance($previousEnv);
        if ($previousNs !== null && $previousNs !== '') {
            $previousEnv->setNs($previousNs);
        }
    }

    /**
     * @param list<string> $namespaces
     *
     * @return array{0: string, 1: ?string}
     */
    private function writeDocumentSource(array $namespaces): array
    {
        $phelFile = $this->createDocumentPath();
        file_put_contents($phelFile, $this->documentSource($namespaces));

        return [$phelFile, Phar::running() !== '' ? dirname($phelFile) : null];
    }

    private function createDocumentPath(): string
    {
        if (Phar::running() !== '') {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Unable to determine current working directory.');
            }

            $tempDir = $cwd . '/.phel_temp_' . uniqid('', true);
            if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                throw new RuntimeException(sprintf('Unable to create temporary directory at "%s".', $tempDir));
            }

            return $tempDir . '/doc.phel';
        }

        $phelDir = __DIR__ . '/phel';
        if (!is_dir($phelDir) && !mkdir($phelDir, 0755, true) && !is_dir($phelDir)) {
            throw new RuntimeException(sprintf('Unable to create directory at "%s".', $phelDir));
        }

        return $phelDir . '/doc.phel';
    }

    /**
     * @param list<string> $namespaces
     */
    private function documentSource(array $namespaces): string
    {
        $requireNamespaces = '';
        foreach ($namespaces as $ns) {
            $requireNamespaces .= sprintf('(:require %s)', $ns);
        }

        return <<<EOF
# Simply require all namespaces that should be documented
(ns phel-internal\doc
  {$requireNamespaces}
)
EOF;
    }
}
