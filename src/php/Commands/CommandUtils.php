<?php

declare(strict_types=1);

namespace Phel\Commands;

use Phel\GlobalEnvironment;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\Lexer;
use Phel\Reader;
use Phel\Runtime;
use RuntimeException;

final class CommandUtils
{
    /**
     * @deprecated Use NamespaceExtractor instead
     */
    public static function getNamespaceFromFile(string $path): string
    {
        $lexer = new Lexer();
        $reader = new Reader(new GlobalEnvironment());
        $content = file_get_contents($path);

        try {
            $tokenStream = $lexer->lexString($content);
            $readerResult = $reader->readNext($tokenStream);

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

    public static function loadRuntime(string $currentDirectory): Runtime
    {
        $runtimePath = $currentDirectory
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'PhelRuntime.php';

        if (file_exists($runtimePath)) {
            return require $runtimePath;
        }

        throw new \RuntimeException('The Runtime could not be loaded from: ' . $runtimePath);
    }

    public static function getPhelConfig(string $currentDirectory): array
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
