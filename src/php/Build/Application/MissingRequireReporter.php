<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\MissingNamespaceException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Munge;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use RuntimeException;
use Throwable;

use function file_get_contents;
use function getcwd;
use function implode;
use function is_file;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Builds the error for a require that resolves to nothing. It points at the
 * required namespace in the requiring file's `ns` form, like an analyzer
 * error, and lists the directories searched. A requirer with no readable
 * source (REPL input, an eval'd string) gets the bare message.
 *
 * @internal
 */
final readonly class MissingRequireReporter
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @param list<string> $searchedDirectories
     */
    public function error(
        string $message,
        string $requiredNs,
        string $requiringFile,
        array $searchedDirectories,
    ): RuntimeException {
        try {
            $located = $this->locate($message, $requiredNs, $requiringFile, $searchedDirectories);
        } catch (Throwable) {
            $located = null;
        }

        return $located ?? new ExtractorException($message);
    }

    /**
     * @param list<string> $searchedDirectories
     */
    private function locate(string $message, string $requiredNs, string $requiringFile, array $searchedDirectories): ?CompilerException
    {
        if ($requiringFile === '' || !is_file($requiringFile)) {
            return null;
        }

        $content = file_get_contents($requiringFile);
        if ($content === false) {
            return null;
        }

        $tokenStream = $this->compilerFacade->lexString($content, $requiringFile);
        do {
            $parseTree = $this->compilerFacade->parseNext($tokenStream);
        } while ($parseTree instanceof TriviaNodeInterface);

        if (!$parseTree instanceof NodeInterface) {
            return null;
        }

        $readerResult = $this->compilerFacade->read($parseTree);
        $symbol = $this->findNamespaceSymbol($readerResult->getAst(), Munge::canonicalNs($requiredNs));
        $start = $symbol?->getStartLocation();
        $end = $symbol?->getEndLocation();
        if (!$start instanceof SourceLocation || !$end instanceof SourceLocation) {
            return null;
        }

        return new CompilerException(
            MissingNamespaceException::at($message, $start, $end, $this->searchedNote($searchedDirectories)),
            $readerResult->getCodeSnippet(),
        );
    }

    private function findNamespaceSymbol(mixed $form, string $requiredNs): ?Symbol
    {
        if ($form instanceof Symbol) {
            return Munge::canonicalNs($form->getFullName()) === $requiredNs ? $form : null;
        }

        if (!$form instanceof PersistentListInterface && !$form instanceof PersistentVectorInterface) {
            return null;
        }

        foreach ($form as $child) {
            $found = $this->findNamespaceSymbol($child, $requiredNs);
            if ($found instanceof Symbol) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param list<string> $directories
     */
    private function searchedNote(array $directories): string
    {
        if ($directories === []) {
            return '';
        }

        $cwd = getcwd();
        $prefix = $cwd === false ? null : rtrim($cwd, '/') . '/';
        $shown = [];
        foreach ($directories as $directory) {
            $shown[] = $prefix !== null && str_starts_with($directory, $prefix)
                ? substr($directory, strlen($prefix))
                : $directory;
        }

        return 'searched: ' . implode(', ', $shown);
    }
}
