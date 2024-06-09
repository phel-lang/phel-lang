<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\IO\FileIoInterface;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Symbol;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final readonly class NamespaceExtractor implements NamespaceExtractorInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private NamespaceSorterInterface $namespaceSorter,
        private FileIoInterface $fileIo,
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
                return new NamespaceInformation(
                    realpath($path),
                    $node->getNamespace(),
                    array_map(
                        static fn (Symbol $s): string => $s->getFullName(),
                        $node->getRequireNs(),
                    ),
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
        /** @var array<string, NamespaceInformation> $namespaces */
        $namespaces = array_reduce(
            $directories,
            function (array $namespaces, string $directory): array {
                foreach ($this->findAllNs($directory) as $ns) {
                    $namespaces += [$ns->getNamespace() => $ns];
                }

                return $namespaces;
            },
            [],
        );

        return $this->sortNamespaceInformationList($namespaces);
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

    /**
     * @throws ExtractorException
     */
    private function findAllNs(string $directory): array
    {
        $realpath = realpath($directory);

        if ($realpath === false) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realpath));
        $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RegexIterator::GET_MATCH);

        return array_map(
            fn (array $file): NamespaceInformation => $this->getNamespaceFromFile($file[0]),
            iterator_to_array($phelIterator),
        );
    }
}
