<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Build\Domain\Port\FileDiscovery\PhelFileDiscoveryPort;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Symbol;
use Phel\Shared\Facade\CompilerFacadeInterface;

use function count;
use function sprintf;

final readonly class NamespaceExtractor implements NamespaceExtractorInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private NamespaceSorterInterface $namespaceSorter,
        private FileIoInterface $fileIo,
        private PhelFileDiscoveryPort $fileDiscovery,
    ) {
    }

    /**
     * @throws ExtractorException
     * @throws LexerValueException
     */
    public function getNamespaceFromFile(string $path): NamespaceInformation
    {
        $content = $this->fileIo->getContents($path);

        try {
            $tokenStream = $this->compilerFacade->lexString($content);
            do {
                $parseTree = $this->compilerFacade->parseNext($tokenStream);
            } while ($parseTree instanceof TriviaNodeInterface);

            if (!$parseTree instanceof NodeInterface) {
                throw ExtractorException::cannotReadFile($path);
            }

            $readerResult = $this->compilerFacade->read($parseTree);
            $ast = $readerResult->getAst();
            $node = $this->compilerFacade->analyze($ast, NodeEnvironment::empty());

            if ($node instanceof NsNode) {
                $realFile = realpath($path);

                return new NamespaceInformation(
                    $realFile !== false ? $realFile : $path,
                    $node->getNamespace(),
                    array_unique(array_map(
                        static fn (Symbol $s): string => $s->getFullName(),
                        $node->getRequireNs(),
                    )),
                    isPrimaryDefinition: true,
                );
            }

            if ($node instanceof InNsNode) {
                $realFile = realpath($path);
                $namespace = $node->getNamespace();

                return new NamespaceInformation(
                    $realFile !== false ? $realFile : $path,
                    $namespace,
                    ($namespace === 'phel\\core') ? [] : ['phel\\core'],
                    isPrimaryDefinition: false,
                );
            }

            throw ExtractorException::cannotExtractNamespaceFromPath($path);
        } catch (AbstractParserException|ReaderException) {
            throw ExtractorException::cannotParseFile($path);
        }
    }

    /**
     * @param list<string> $directories
     *
     * @throws ExtractorException
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories): array
    {
        $allFiles = $this->fileDiscovery->findPhelFiles($directories);

        /** @var array<string, NamespaceInformation> $namespaces */
        $namespaces = [];
        /** @var array<string, list<string>> $primaryDefinitions */
        $primaryDefinitions = [];

        foreach ($allFiles as $file) {
            $ns = $this->getNamespaceFromFile($file);
            $namespace = $ns->getNamespace();
            if ($ns->isPrimaryDefinition()) {
                $primaryDefinitions[$namespace][] = $ns->getFile();
            }

            $namespaces[$namespace] = $ns;
        }

        $this->warnAboutDuplicateNamespaces($primaryDefinitions);

        return $this->sortNamespaceInformationList(array_values($namespaces));
    }

    /**
     * @param array<string, list<string>> $allLocations
     */
    private function warnAboutDuplicateNamespaces(array $allLocations): void
    {
        foreach ($allLocations as $namespace => $files) {
            if (count($files) > 1) {
                $fileList = implode("\n", array_map(static fn (string $f): string => '  - ' . $f, $files));
                fwrite(STDERR, sprintf(
                    "\nWARNING: Namespace '%s' is defined in multiple locations:\n%s\n" .
                    "The last one will be used. Check your phel-config.php srcDirs/testDirs settings.\n",
                    $namespace,
                    $fileList,
                ));
            }
        }
    }

    /**
     * @param list<NamespaceInformation> $namespaceInformationList
     *
     * @return list<NamespaceInformation>
     */
    private function sortNamespaceInformationList(array $namespaceInformationList): array
    {
        $dependencyIndex = [];
        $infoIndex = [];
        foreach ($namespaceInformationList as $info) {
            $dependencyIndex[$info->getNamespace()] = $info->getDependencies();
            $infoIndex[$info->getNamespace()] = $info;
        }

        $orderedNamespaces = $this->namespaceSorter->sort(array_keys($dependencyIndex), $dependencyIndex);

        $result = [];
        foreach ($orderedNamespaces as $namespace) {
            if (isset($infoIndex[$namespace])) {
                $result[] = $infoIndex[$namespace];
            }
        }

        return $result;
    }
}
