<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phar;
use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use RuntimeException;

use function dirname;
use function getcwd;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final readonly class PhelFunctionRuntimeLoader
{
    public function __construct(
        private RunFacadeInterface $runFacade,
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @param list<string> $namespaces
     */
    public function load(array $namespaces): void
    {
        $previousEnv = $this->compilerFacade->isGlobalEnvironmentInitialized()
            ? $this->compilerFacade->getGlobalEnvironment()
            : null;
        $previousRegistry = $previousEnv instanceof GlobalEnvironmentInterface
            ? Registry::getInstance()->snapshot()
            : null;
        $previousNs = $previousEnv?->getNs();

        Phel::clear();
        Symbol::resetGen();
        $this->compilerFacade->initializeNewGlobalEnvironment();

        [$phelFile, $tempDir] = $this->writeDocumentSource($namespaces);

        try {
            $namespace = $this->runFacade
                ->getNamespaceFromFile($phelFile)
                ->getNamespace();

            // Seed the topological sort with every requested namespace
            // (in addition to the temp doc namespace and core) so a stray
            // parse / require failure on the generated doc.phel cannot
            // silently drop one of them. CI saw `phel.async` go missing
            // intermittently otherwise.
            $namespaceInformation = $this->runFacade->getDependenciesForNamespace(
                [
                    dirname($phelFile),
                    ...$this->runFacade->getAllPhelDirectories(),
                ],
                [$namespace, 'phel.core', ...$namespaces],
            );

            foreach ($namespaceInformation as $info) {
                $this->runFacade->evalFile($info);
            }
        } finally {
            unlink($phelFile);

            if ($tempDir !== null && is_dir($tempDir)) {
                rmdir($tempDir);
            }

            if ($previousEnv instanceof GlobalEnvironmentInterface && $previousRegistry !== null) {
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

        // User-defined entries win on key collisions: a `(def map ...)` in the
        // REPL must survive a doc/completion reload of `phel\core`.
        $mergedDefinitions = $current['definitions'];
        foreach ($previousRegistry['definitions'] as $ns => $entries) {
            $mergedDefinitions[$ns] = $entries + ($mergedDefinitions[$ns] ?? []);
        }

        $mergedMeta = $current['definitionsMetaData'];
        foreach ($previousRegistry['definitionsMetaData'] as $ns => $entries) {
            $mergedMeta[$ns] = $entries + ($mergedMeta[$ns] ?? []);
        }

        $registry->restore([
            'definitions' => $mergedDefinitions,
            'definitionsMetaData' => $mergedMeta,
        ]);

        $this->compilerFacade->setGlobalEnvironment($previousEnv);
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

        return [$phelFile, dirname($phelFile)];
    }

    private function createDocumentPath(): string
    {
        // A unique per-call dir keeps concurrent processes (paratest workers,
        // parallel `phel doc` invocations) from clobbering a shared doc.phel,
        // and keeps the generated file out of the (possibly read-only) package
        // tree. Inside a PHAR sys_get_temp_dir() is fine too, but the cwd keeps
        // the generated namespace resolvable against the project's own sources.
        $baseDir = Phar::running() !== '' ? $this->currentWorkingDir() : sys_get_temp_dir();

        $tempDir = $baseDir . '/.phel_temp_' . uniqid('', true);
        if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Unable to create temporary directory at "%s".', $tempDir));
        }

        return $tempDir . '/doc.phel';
    }

    private function currentWorkingDir(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to determine current working directory.');
        }

        return $cwd;
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
