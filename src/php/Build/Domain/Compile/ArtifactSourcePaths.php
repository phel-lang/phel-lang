<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Cache\SourceFilePath;
use Phel\Shared\SourceMap\SourceMapSiblings;
use Phel\Shared\SourceMap\SourcePathMarker;
use RuntimeException;

use function count;
use function dirname;
use function in_array;
use function is_array;
use function strlen;

/**
 * @internal
 */
final readonly class ArtifactSourcePaths
{
    /** @var array<string, string> */
    private array $targets;

    /** @var array<string, string> */
    private array $originalSources;

    /** @var list<string> */
    private array $compiledFiles;

    /**
     * @param array<string, string> $compiledFiles
     */
    public function __construct(array $compiledFiles)
    {
        $targets = [];
        $originalSources = [];
        $files = [];
        foreach ($compiledFiles as $source => $compiled) {
            $source = SourceFilePath::normalize($source);
            $compiled = SourceFilePath::normalize($compiled);
            $copy = SourceMapSiblings::sourceFile($compiled);
            $targets[$source] = $copy;
            $originalSources[$copy] = $source;
            $files[] = $compiled;
        }

        $this->targets = $targets;
        $this->originalSources = $originalSources;
        $this->compiledFiles = $files;
    }

    /**
     * @return list<string>
     */
    public function compiledFiles(): array
    {
        return $this->compiledFiles;
    }

    /**
     * @return array<string, string>
     */
    public function originalSources(): array
    {
        return $this->originalSources;
    }

    public function rewrite(string $phpCode, string $compiledFile): string
    {
        return $this->rewriteWithMap($phpCode, $compiledFile, '')['code'];
    }

    /**
     * @return array{code: string, map: string}
     */
    public function rewriteWithMap(string $phpCode, string $compiledFile, string $sourceMap): array
    {
        $tokens = token_get_all($phpCode);
        $replacements = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_COMMENT && ($marker = SourcePathMarker::decode($token[1])) !== null) {
                $valueIndex = $this->nextToken($tokens, $index + 1);
                if ($this->stringValue($tokens[$valueIndex] ?? null) !== null) {
                    $expression = $this->expression($marker['file'], $compiledFile, !$marker['directory']);
                    if ($expression === null) {
                        throw new RuntimeException('Cannot relocate source path ' . $marker['file']);
                    }

                    $replacements[$index] = '';
                    $replacements[$valueIndex] = $marker['directory'] ? 'dirname(' . $expression . ')' : $expression;
                }

                continue;
            }

            if ($token[1] === '\\Phel') {
                $colon = $this->nextToken($tokens, $index + 1);
                $method = $this->nextToken($tokens, $colon + 1);
                $opening = $this->nextToken($tokens, $method + 1);
                if (($tokens[$colon][0] ?? null) !== T_DOUBLE_COLON || ($tokens[$opening] ?? null) !== '(') {
                    continue;
                }

                $arguments = $this->arguments($tokens, $opening);
                if (($tokens[$method][1] ?? null) === 'location' && isset($arguments[0])) {
                    $this->replacePath($tokens, $arguments[0], $compiledFile, $replacements);
                } elseif (($tokens[$method][1] ?? null) === 'addDefinition'
                    && isset($arguments[0], $arguments[1], $arguments[2])
                    && $this->stringValue($tokens[$arguments[0]] ?? null) === 'phel.core'
                    && $this->stringValue($tokens[$arguments[1]] ?? null) === '*file*'
                ) {
                    $this->replacePath($tokens, $arguments[2], $compiledFile, $replacements);
                }
            }

            if ($token[0] === T_VARIABLE && $token[1] === '$__phelCallerSrcDir') {
                $equals = $this->nextToken($tokens, $index + 1);
                $value = $this->nextToken($tokens, $equals + 1);
                $directory = $this->stringValue($tokens[$value] ?? null);
                $source = $this->originalSources[SourceMapSiblings::sourceFile(SourceFilePath::normalize($compiledFile))] ?? null;
                if (($tokens[$equals] ?? null) === '=' && $directory !== null && $source !== null
                    && dirname(SourceFilePath::normalize($directory . '/source')) === dirname($source)
                ) {
                    $expression = $this->expression($source, $compiledFile);
                    if ($expression !== null) {
                        $replacements[$value] = 'dirname(' . $expression . ')';
                    }
                }
            }

            if ($token[0] === T_VARIABLE && $token[1] === '$__phelCandidatePhel') {
                $equals = $this->nextToken($tokens, $index + 1);
                if (($tokens[$equals] ?? null) !== '=') {
                    continue;
                }

                $suffixIndex = null;
                $alreadyPortable = false;
                $callerRelative = false;
                for ($cursor = $equals + 1; isset($tokens[$cursor]) && $tokens[$cursor] !== ';'; ++$cursor) {
                    if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_VARIABLE && $tokens[$cursor][1] === '$__phelBuildMode') {
                        $alreadyPortable = true;
                    }

                    if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_VARIABLE && $tokens[$cursor][1] === '$__phelCallerSrcDir') {
                        $callerRelative = true;
                    }

                    if ($this->stringValue($tokens[$cursor]) === '.phel') {
                        $suffixIndex = $cursor;
                    }
                }

                if ($callerRelative && !$alreadyPortable && $suffixIndex !== null) {
                    $replacements[$suffixIndex] = "(!\$__phelBuildMode && is_file(\$__phelCallerSrcDir . '/' . \$__phelAttempt . '.php') ? '.php' : '.phel')";
                }
            }
        }

        $result = '';
        $edits = [];
        $line = 1;
        $column = 0;
        foreach ($tokens as $index => $token) {
            $text = is_array($token) ? $token[1] : $token;
            if (isset($replacements[$index])) {
                $edits[$line][] = ['column' => $column, 'length' => strlen($text), 'replacementLength' => strlen($replacements[$index])];
            }

            $result .= $replacements[$index] ?? $text;
            $newLines = substr_count($text, "\n");
            if ($newLines > 0) {
                $line += $newLines;
                $column = strlen($text) - (int) strrpos($text, "\n") - 1;
            } else {
                $column += strlen($text);
            }
        }

        return ['code' => $result, 'map' => ArtifactSourceMap::rewrite($sourceMap, $edits)];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     * @param array<int, string>                   $replacements
     */
    private function replacePath(array $tokens, int $index, string $compiledFile, array &$replacements): void
    {
        $file = $this->stringValue($tokens[$index] ?? null);
        if ($file === null) {
            return;
        }

        $next = $this->nextToken($tokens, $index + 1);
        if (($tokens[$next] ?? null) !== ',' && ($tokens[$next] ?? null) !== ')') {
            return;
        }

        $expression = $this->expression($file, $compiledFile);
        if ($expression !== null) {
            $replacements[$index] = $expression;
        }
    }

    private function expression(string $source, string $compiledFile, bool $realFile = false): ?string
    {
        $target = $this->targets[SourceFilePath::normalize($source)] ?? null;
        if ($target === null) {
            return null;
        }

        $base = explode('/', str_replace('\\', '/', dirname(SourceFilePath::normalize($compiledFile))));
        $destination = explode('/', str_replace('\\', '/', $target));
        while ($base !== [] && $destination !== [] && $base[0] === $destination[0]) {
            array_shift($base);
            array_shift($destination);
        }

        $relative = str_repeat('../', count($base)) . implode('/', $destination);

        $literal = str_replace(["\r", "\n"], ["' . \"\\r\" . '", "' . \"\\n\" . '"], var_export('/' . $relative, true));

        return '\\' . ArtifactSourcePath::class . '::resolve(__DIR__ . ' . $literal
            . ($realFile ? ', true' : '') . ')';
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return list<int>
     */
    private function arguments(array $tokens, int $opening): array
    {
        $arguments = [$this->nextToken($tokens, $opening + 1)];
        $depth = 0;
        for ($index = $opening; isset($tokens[$index]); ++$index) {
            $token = $tokens[$index];
            if (in_array($token, ['(', '[', '{'], true)) {
                ++$depth;
            } elseif (in_array($token, [')', ']', '}'], true)) {
                --$depth;
                if ($depth === 0) {
                    break;
                }
            } elseif ($token === ',' && $depth === 1) {
                $arguments[] = $this->nextToken($tokens, $index + 1);
            }
        }

        return $arguments;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function nextToken(array $tokens, int $index): int
    {
        while (isset($tokens[$index]) && is_array($tokens[$index]) && $tokens[$index][0] === T_WHITESPACE) {
            ++$index;
        }

        return $index;
    }

    /**
     * @param array{int, string, int}|string|null $token
     */
    private function stringValue(array|string|null $token): ?string
    {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $value = substr($token[1], 1, -1);

        return $token[1][0] === '"' ? stripcslashes($value) : strtr($value, ['\\\\' => '\\', "\\'" => "'"]);
    }
}
