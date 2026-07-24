<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Config;

use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lint\Domain\Exception\LintConfigException;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function count;
use function file_get_contents;
use function is_file;
use function is_string;

/**
 * Loads a `.phel-lint.phel` EDN-style config into a `RuleSettings`
 * instance. Missing files are not an error: callers just get defaults.
 * A file that exists but cannot be read, parsed, or is not a map raises
 * a {@see LintConfigException} — linting with the defaults would silently
 * ignore what the user asked for.
 *
 * Expected shape (Phel map of keywords):
 *
 * ```phel
 * {:rules {:phel/unused-binding :off
 *          :phel/arity-mismatch :error}
 *  :exclude {:phel/unused-binding ["src/phel/local.phel" "phel.experimental.*"]}}
 * ```
 */
final readonly class ConfigLoader
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @throws LintConfigException
     */
    public function load(string $path, RuleSettings $defaults): RuleSettings
    {
        if (!is_file($path)) {
            return $defaults;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw LintConfigException::cannotRead($path);
        }

        $map = $this->parseFirstForm($contents, $path);
        if ($map === null) {
            // Empty config file: nothing to override.
            return $defaults;
        }

        if (!$map instanceof PersistentMapInterface) {
            throw LintConfigException::notAMap($path);
        }

        $severities = $this->extractSeverities($map);
        $excludes = $this->extractExcludes($map);

        return $defaults->withOverrides($severities, $excludes);
    }

    /**
     * Reads the first non-trivia form of the config file. Returns `null` when
     * the file holds no data form at all (empty or comments only).
     *
     * @throws LintConfigException on a lex/parse/read failure
     */
    private function parseFirstForm(string $source, string $uri): mixed
    {
        try {
            $tokenStream = $this->compilerFacade->lexString($source, $uri);
            while (true) {
                $parseTree = $this->compilerFacade->parseNext($tokenStream);

                if (!$parseTree instanceof NodeInterface) {
                    return null;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                return $this->compilerFacade->read($parseTree)->getAst();
            }
        } catch (AbstractParserException|ReaderException|LexerValueException $e) {
            throw LintConfigException::cannotParse($uri, $e);
        }
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $map
     *
     * @return array<string, string>
     */
    private function extractSeverities(PersistentMapInterface $map): array
    {
        $rulesNode = $map->find(Keyword::create('rules'));
        if (!$rulesNode instanceof PersistentMapInterface) {
            return [];
        }

        $result = [];
        foreach ($rulesNode as $key => $value) {
            $code = $this->keywordOrStringName($key);
            $severity = $this->keywordOrStringName($value);
            if ($code === null) {
                continue;
            }

            if ($severity === null) {
                continue;
            }

            $result[$code] = $severity;
        }

        return $result;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $map
     *
     * @return array<string, list<string>>
     */
    private function extractExcludes(PersistentMapInterface $map): array
    {
        $excludesNode = $map->find(Keyword::create('exclude'));
        if (!$excludesNode instanceof PersistentMapInterface) {
            return [];
        }

        $result = [];
        foreach ($excludesNode as $key => $value) {
            $code = $this->keywordOrStringName($key);
            if ($code === null) {
                continue;
            }

            $patterns = $this->collectStringPatterns($value);
            if ($patterns !== []) {
                $result[$code] = $patterns;
            }
        }

        return $result;
    }

    private function keywordOrStringName(mixed $value): ?string
    {
        if ($value instanceof Keyword) {
            $ns = $value->getNamespace();
            $name = $value->getName();

            return $ns === null || $ns === '' ? $name : $ns . '/' . $name;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function collectStringPatterns(mixed $value): array
    {
        $result = [];
        if ($value instanceof PersistentVectorInterface || $value instanceof PersistentListInterface) {
            $size = count($value);
            for ($i = 0; $i < $size; ++$i) {
                $item = $value->get($i);
                if (is_string($item) && $item !== '') {
                    $result[] = $item;
                }
            }
        } elseif (is_string($value) && $value !== '') {
            $result[] = $value;
        }

        return $result;
    }
}
