<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Generator;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\TypeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use Throwable;

/**
 * Backs {@see \Phel\Shared\Facade\CompilerFacadeInterface::readFormsBestEffort()},
 * which documents the contract and when to prefer the throwing single-stage hooks.
 */
final readonly class BestEffortFormReader
{
    public function __construct(
        private LexerInterface $lexer,
        private ParserInterface $parser,
        private ReaderInterface $reader,
    ) {}

    /**
     * @return Generator<int, bool|float|int|string|TypeInterface|null>
     */
    public function readForms(string $code, string $source): Generator
    {
        try {
            $tokenStream = $this->lexer->lexString($code, $source);

            while (true) {
                try {
                    $parseTree = $this->parser->parseNext($tokenStream);
                } catch (AbstractParserException) {
                    return;
                }

                if (!$parseTree instanceof NodeInterface) {
                    return;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                try {
                    $readerResult = $this->reader->read($parseTree);
                } catch (ReaderException) {
                    continue;
                }

                // `getAst()` is `mixed`; the reader contract is this union.
                /** @var bool|float|int|string|TypeInterface|null $form */
                $form = $readerResult->getAst();

                yield $form;
            }
        } catch (Throwable) {
            // Best-effort: the caller keeps whatever it consumed so far.
        }
    }
}
