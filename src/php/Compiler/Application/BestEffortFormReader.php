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
 *
 * @internal
 */
final readonly class BestEffortFormReader
{
    public function __construct(
        private LexerInterface $lexer,
        private ParserInterface $parser,
        private ReaderInterface $reader,
    ) {}

    /**
     * The generator's return value says whether anything was dropped, so a
     * caller can tell a buffer with nothing to report from one nobody could
     * read. `phel lint` called a file that does not even lex clean without it
     * (#3292). Read it with `Generator::getReturn()` once the loop ends.
     *
     * @return Generator<int, bool|float|int|string|TypeInterface|null, mixed, bool>
     */
    public function readForms(string $code, string $source): Generator
    {
        $droppedSomething = false;

        try {
            $tokenStream = $this->lexer->lexString($code, $source);

            while (true) {
                try {
                    $parseTree = $this->parser->parseNext($tokenStream);
                } catch (AbstractParserException) {
                    return true;
                }

                if (!$parseTree instanceof NodeInterface) {
                    return $droppedSomething;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                try {
                    $readerResult = $this->reader->read($parseTree);
                } catch (ReaderException) {
                    $droppedSomething = true;
                    continue;
                }

                // `getAst()` is `mixed`; the reader contract is this union.
                /** @var bool|float|int|string|TypeInterface|null $form */
                $form = $readerResult->getAst();

                yield $form;
            }
        } catch (Throwable) {
            // Best-effort: the caller keeps whatever it consumed so far.
            return true;
        }
    }
}
