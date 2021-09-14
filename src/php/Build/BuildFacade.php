<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFacade;
use Phel\Build\Compile\CompiledFile;
use Phel\Build\Extractor\NamespaceInformation;

/**
 * @method BuildFactory getFactory()
 */
final class BuildFacade extends AbstractFacade implements BuildFacadeInterface
{
    /**
     * Extracts the namespace from a given file. It expects that the
     * first statement in the file is the 'ns statement.
     *
     * @param string $filename The path to the file
     *
     * @return NamespaceInformation
     */
    public function getNamespaceFromFile(string $filename): NamespaceInformation
    {
        return $this->getFactory()
            ->createNamespaceExtractor()
            ->getNamespaceFromFile($filename);
    }

    /**
     * Extracts all namespaces from all Phel files in the given directories.
     * It expects that the first statement in the file is the 'ns statement.
     *
     * @param string[] $directories The list of the directories
     *
     * @return NamespaceInformation[]
     */
    public function getNamespaceFromDirectories(array $directories): array
    {
        return $this->getFactory()
            ->createNamespaceExtractor()
            ->getNamespacesFromDirectories($directories);
    }

    /**
     * Gets a list of all dependencies for a given list of namespaces. It first extracts all
     * namespaces from all Phel files in the give directories and then return a
     * topoloigcal sorted subset of these namespace information.
     *
     * @param string[] $directories The list of the directories
     * @param string[] $ns A list of namespace for which we should find the subset
     *
     * @return NamespaceInformation[]
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        $namespaceInformation = $this->getNamespaceFromDirectories($directories);

        $index = [];
        $queue = [];
        foreach ($namespaceInformation as $info) {
            $index[$info->getNamespace()] = $info;
            if (in_array($info->getNamespace(), $ns)) {
                $queue[] = $info->getNamespace();
            }
        }

        $requiredNamespaces = [];
        while (count($queue) > 0) {
            $currentNs = array_shift($queue);
            if (!isset($requiredNamespaces[$currentNs])) {
                foreach ($index[$currentNs]->getDependencies() as $depNs) {
                    $queue[] = $depNs;
                }
            }
            $requiredNamespaces[$currentNs] = true;
        }

        $result = [];
        foreach ($namespaceInformation as $info) {
            if (isset($requiredNamespaces[$info->getNamespace()])) {
                $result[] = $info;
            }
        }

        return $result;
    }

    /**
     * Compiles a phel file and saves it to the give destination.
     *
     * @param string $src The source file
     * @param string $dest The destination
     *
     * @return CompiledFile
     */
    public function compileFile(string $src, string $dest): CompiledFile
    {
        $compiledCode = $this->getFactory()->getCompilerFacade()->compile(
            file_get_contents($src),
            $src,
            true
        );

        file_put_contents($dest, "<?php\n" . $compiledCode);

        return new CompiledFile(
            $src,
            $dest,
            $this->getNamespaceFromFile($src)->getNamespace()
        );
    }

    /**
     * Same as `compileFile`. However, the generated code is not written to a destination.
     *
     * @param string $src The source file
     *
     * @return CompiledFile
     */
    public function evalFile(string $src): CompiledFile
    {
        $this->getFactory()->getCompilerFacade()->compile(
            file_get_contents($src),
            $src,
            true
        );

        return new CompiledFile(
            $src,
            '',
            $this->getNamespaceFromFile($src)->getNamespace()
        );
    }

    /**
     * Compiles all Phel files that can be found in the give source directories
     * and saves them into the target directory.
     *
     * @param string[] $srcDirectories The list of source directories
     * @param string $dest the target dir that should contain the generated code
     *
     * @return CompiledFile[] A list of compiled files
     */
    public function compileProject(array $srcDirectories, string $dest): array
    {
        $namespaceInformation = $this->getNamespaceFromDirectories($srcDirectories);

        $result = [];
        foreach ($namespaceInformation as $info) {
            $targetFile = $dest . '/' . $this->getTargetFileFromNamespace($info->getNamespace());
            $targetDir = dirname($targetFile);
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $result[] = $this->compileFile($info->getFile(), $targetFile);
        }

        return $result;
    }

    private function getTargetFileFromNamespace(string $namespace): string
    {
        return implode(DIRECTORY_SEPARATOR, explode('\\', $namespace)) . '.php';
    }
}
