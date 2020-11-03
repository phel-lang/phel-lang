<?php

declare(strict_types=1);

namespace Phel\Commands\Utils;

use Phel\Commands\Run\RunCommandIoInterface;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\LexerInterface;
use Phel\ReaderInterface;
use RuntimeException;

final class NamespaceExtractor implements NamespaceExtractorInterface
{
    private LexerInterface $lexer;
    private ReaderInterface $reader;
    private RunCommandIoInterface $io;

    public function __construct(
        LexerInterface $lexer,
        ReaderInterface $reader,
        RunCommandIoInterface $io
    ) {
        $this->lexer = $lexer;
        $this->reader = $reader;
        $this->io = $io;
    }

    public function getNamespaceFromFile(string $path): string
    {
        $content = $this->io->fileGetContents($path);

        try {
            $tokenStream = $this->lexer->lexString($content);
            $readerResult = $this->reader->readNext($tokenStream);

            if (!$readerResult) {
                throw new RuntimeException('Cannot read file: ' . $path);
            }

            $ast = $readerResult->getAst();

            if ($ast instanceof Tuple
                && $ast[0] instanceof Symbol
                && $ast[1] instanceof Symbol
                && $ast[0]->getName() === Symbol::NAME_NS
            ) {
                return $ast[1]->getName();
            }

            throw new RuntimeException('Cannot extract namespace from file: ' . $path);
        } catch (\Phel\Exceptions\ReaderException $e) {
            throw new RuntimeException('Cannot parse file: ' . $path);
        }
    }
}
