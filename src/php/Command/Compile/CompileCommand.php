<?php

declare(strict_types=1);

namespace Phel\Command\Compile;

use Phel\Compiler\CompilerFacadeInterface;
use Phel\NamespaceExtractor\NamespaceExtractorFacadeInterface;
use Phel\Runtime\RuntimeFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CompileCommand extends Command
{
    public const COMMAND_NAME = 'compile';
    private CompilerFacadeInterface $compilerFacade;
    private NamespaceExtractorFacadeInterface $namespaceExtractorFacade;
    private TopologicalSorting $topologicalSorting;
    private RuntimeFacadeInterface $runtimeFacade;

    public function __construct(
        CompilerFacadeInterface $compilerFacade,
        NamespaceExtractorFacadeInterface $namespaceExtractorFacade,
        RuntimeFacadeInterface $runtimeFacade,
        TopologicalSorting $topologicalSorting
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->compilerFacade = $compilerFacade;
        $this->namespaceExtractorFacade = $namespaceExtractorFacade;
        $this->runtimeFacade = $runtimeFacade;
        $this->topologicalSorting = $topologicalSorting;
    }

    protected function configure(): void
    {
        $this->setDescription('Compiles all files in the project to PHP code.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetDir = ''; // TODO: Get target dir form config or input argument

        $this->compileProject(
            array_merge(...array_values($this->runtimeFacade->getRuntime()->getPaths())),
            $targetDir
        );

        return self::SUCCESS;
    }

    /**
     * @param string[] $srcDirectories
     * @param string $targetDir
     */
    private function compileProject(array $srcDirectories, string $targetDir): void
    {
        $namespaces = $this->namespaceExtractorFacade->getNamespaceFromDirectories($srcDirectories);

        $dependencyIndex = [];
        $fileIndex = [];
        foreach ($namespaces as $info) {
            $dependencyIndex[$info->getNamespace()] = $info->getDependencies();
            $fileIndex[$info->getNamespace()] = $info->getFile();
        }

        $orderedNamespaces = $this->topologicalSorting->sort(array_keys($dependencyIndex), $dependencyIndex);
        $orderedFiles = array_map(fn (string $ns) => $fileIndex[$ns], $orderedNamespaces);

        foreach ($orderedFiles as $i => $file) {
            $this->compileFile(
                $file,
                $targetDir . '/' . $this->getTargetFileFromNamespace($targetDir, $orderedNamespaces[$i])
            );
        }
    }

    private function compileFile(string $source, string $dest): void
    {
        echo "Compiling $source to $dest\n";
        $compiledCode = $this->compilerFacade->compile(
            file_get_contents($source),
            $source,
            true,
        );

        //file_put_contents($dest, $compiledCode);
        echo "Compiling $source to $dest\n";
    }

    private function getTargetFileFromNamespace(string $targetDir, string $namespace): string
    {
        return $targetDir . implode(DIRECTORY_SEPARATOR, explode('\\', $namespace)) . '.php';
    }
}
