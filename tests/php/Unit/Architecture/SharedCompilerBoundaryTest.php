<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function sprintf;

/**
 * `Phel\Shared` is the leaf contract layer, so every other module may point at
 * it. `CompilerFacadeInterface` points back, because the compiler's public
 * surface is genuinely expressed in compiler types (AST nodes, the analyzer
 * environment, the token stream).
 *
 * That back-edge is accepted deliberately; see the "Compiler Back-Edge"
 * section of `src/php/Shared/CLAUDE.md` for the rationale. What must NOT happen
 * is the edge quietly widening: a second Shared file reaching into Compiler, or
 * a new compiler type leaking into the contract. Both are locked here.
 *
 * @see \Phel\Shared\Facade\CompilerFacadeInterface
 */
final class SharedCompilerBoundaryTest extends TestCase
{
    /**
     * The only file in `Shared` allowed to import from `Phel\Compiler`,
     * relative to `src/php/Shared`.
     */
    private const string ALLOWED_FILE = 'Facade/CompilerFacadeInterface.php';

    /**
     * Every compiler symbol the contract is allowed to name. Six carry the
     * compiler's public types through method signatures; five appear only in
     * `@throws` tags. Adding to this list widens the cycle, so it should be a
     * deliberate, reviewed act rather than a silent import.
     *
     * @var list<string>
     */
    private const array ALLOWED_IMPORTS = [
        AbstractNode::class,
        GlobalEnvironmentInterface::class,
        NodeEnvironmentInterface::class,
        AnalyzerException::class,
        EmitterResult::class,
        LexerValueException::class,
        TokenStream::class,
        UnexpectedParserException::class,
        UnfinishedParserException::class,
        ReaderResult::class,
        ReaderException::class,
    ];

    public function test_compiler_facade_interface_is_the_only_shared_file_importing_compiler(): void
    {
        $offenders = array_keys($this->compilerImportsPerSharedFile());

        self::assertSame(
            [self::ALLOWED_FILE],
            $offenders,
            sprintf(
                "Only %s may import Phel\\Compiler.\nThe Shared -> Compiler cycle is accepted only because it is "
                . "confined to that one contract; a second back-edge makes it structural.\n"
                . 'See the "Compiler Back-Edge" section of src/php/Shared/CLAUDE.md.',
                self::ALLOWED_FILE,
            ),
        );
    }

    public function test_the_compiler_contract_names_no_unlisted_compiler_types(): void
    {
        $imports = $this->compilerImportsPerSharedFile()[self::ALLOWED_FILE] ?? [];
        sort($imports);

        $expected = self::ALLOWED_IMPORTS;
        sort($expected);

        self::assertSame(
            $expected,
            $imports,
            "The set of compiler types reachable from Phel\\Shared changed.\n"
            . "Removing one is good news: drop it from ALLOWED_IMPORTS.\n"
            . 'Adding one widens the cycle: justify it in src/php/Shared/CLAUDE.md first.',
        );
    }

    public function test_the_documented_edge_count_matches_reality(): void
    {
        // The CLAUDE.md rationale quotes a concrete number; keep the two honest
        // about each other so the prose cannot silently drift from the code.
        self::assertCount(11, self::ALLOWED_IMPORTS);
    }

    /**
     * @return array<string, list<string>> relative file path => imported compiler FQNs
     */
    private function compilerImportsPerSharedFile(): array
    {
        $sharedDir = dirname(__DIR__, 4) . '/src/php/Shared';
        self::assertDirectoryExists($sharedDir);

        $found = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sharedDir)) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all('/^use\s+(Phel\\\\Compiler\\\\[^;\s]+)\s*;/m', $contents, $matches);

            if ($matches[1] === []) {
                continue;
            }

            $relative = str_replace($sharedDir . '/', '', $file->getPathname());
            $found[$relative] = $matches[1];
        }

        ksort($found);

        return $found;
    }
}
