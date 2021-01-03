<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ReaderInterface;
use Phel\Exceptions\ParserException;
use Phel\Exceptions\ReaderException;
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

    public function __construct(
        LexerInterface $lexer,
        ParserInterface $parser,
        ReaderInterface $reader,
        CommandIoInterface $io
    ) {
        $this->lexer = $lexer;
        $this->parser = $parser;
        $this->reader = $reader;
        $this->io = $io;
    }

    public function getNamespaceFromFile(string $path): string
    {
        $content = $this->io->fileGetContents($path);

        try {
            $tokenStream = $this->lexer->lexString($content);
            do {
                $parseTree = $this->parser->parseNext($tokenStream);
            } while ($parseTree && $parseTree instanceof TriviaNodeInterface);

            if (!$parseTree) {
                throw new RuntimeException('Cannot read file: ' . $path);
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

            throw new RuntimeException('Cannot extract namespace from file: ' . $path);
        } catch (ParserException $e) {
            throw new RuntimeException('Cannot parse file: ' . $path);
        } catch (ReaderException $e) {
            throw new RuntimeException('Cannot parse file: ' . $path);
        }
    }

    public function getNamespacesFromConfig(string $currentDir): array
    {
        $config = $this->getPhelConfig($currentDir);
        $namespaces = [];

        $testDirectories = $config['tests'] ?? [];
        foreach ($testDirectories as $testDir) {
            $allNamespacesInDir = $this->findAllNs($currentDir . $testDir);
            $namespaces = array_merge($namespaces, $allNamespacesInDir);
        }

        return $namespaces;
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

    private function getPhelConfig(string $currentDirectory): array
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
}
