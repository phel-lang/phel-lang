<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

use Phel\Command\Shared\Exceptions\ExtractorException;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\ReaderInterface;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use RuntimeException;

final class NamespaceExtractor implements NamespaceExtractorInterface
{
    private LexerInterface $lexer;
    private ParserInterface $parser;
    private ReaderInterface $reader;
    private CommandIoInterface $io;
    /** @var list<string> */
    private array $testDirectories;

    public function __construct(
        LexerInterface $lexer,
        ParserInterface $parser,
        ReaderInterface $reader,
        CommandIoInterface $io,
        array $testDirectories
    ) {
        $this->lexer = $lexer;
        $this->parser = $parser;
        $this->reader = $reader;
        $this->io = $io;
        $this->testDirectories = $testDirectories;
    }

    /**
     * @throws ExtractorException
     */
    public function getNamespaceFromFile(string $path): string
    {
        $content = $this->io->fileGetContents($path);

        try {
            $tokenStream = $this->lexer->lexString($content);
            do {
                $parseTree = $this->parser->parseNext($tokenStream);
            } while ($parseTree && $parseTree instanceof TriviaNodeInterface);

            if (!$parseTree) {
                throw ExtractorException::cannotReadFile($path);
            }

            $readerResult = $this->reader->read($parseTree);
            $ast = $readerResult->getAst();

            if ($ast instanceof Tuple
                && $ast[0] instanceof Symbol
                && $ast[1] instanceof Symbol
                && $ast[0]->getName() === Symbol::NAME_NS
            ) {
                return $ast[1]->getName();
            }

            throw ExtractorException::cannotExtractNamespaceFromPath($path);
        } catch (AbstractParserException|ReaderException $e) {
            throw ExtractorException::cannotParseFile($path);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function getNamespacesFromConfig(string $projectRootDir): array
    {
        $namespaces = [];
        foreach ($this->testDirectories as $testDir) {
            $allNamespacesInDir = $this->findAllNs($projectRootDir . $testDir);
            $namespaces[] = $allNamespacesInDir;
        }

        return array_merge(...array_values($namespaces));
    }

    private function findAllNs(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RecursiveRegexIterator::GET_MATCH);

        return array_map(
            fn ($file) => $this->getNamespaceFromFile($file[0]),
            iterator_to_array($phelIterator)
        );
    }
}
