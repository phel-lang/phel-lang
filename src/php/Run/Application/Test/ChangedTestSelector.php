<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Run\Domain\Test\AffectedTestNamespaces;
use Phel\Run\Domain\Test\ChangedFilesFinderInterface;
use Phel\Run\Domain\Test\ChangeSelection;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\NamespaceInformation;
use Throwable;

use function is_file;
use function str_ends_with;

/**
 * `--changed`: asks git for the changed files, resolves each `.phel` one to
 * its namespace (a secondary `(in-ns ...)` file resolves to the namespace it
 * joins, which the run's namespace list does not carry), and hands the
 * namespaces to the pure selection.
 *
 * @internal
 */
final readonly class ChangedTestSelector
{
    public function __construct(
        private ChangedFilesFinderInterface $changedFiles,
        private AffectedTestNamespaces $affected,
        private BuildFacadeInterface $buildFacade,
        private CommandFacadeInterface $commandFacade,
    ) {}

    /**
     * @param list<NamespaceInformation> $infos
     */
    public function select(?string $ref, array $infos, string $projectDir): ChangeSelection
    {
        $changedFiles = [];
        $changedNamespaces = [];
        foreach ($this->changedFiles->changedFiles($ref, $projectDir) as $file) {
            if (!str_ends_with($file, '.phel')) {
                continue;
            }

            if (!is_file($file)) {
                continue;
            }

            try {
                $changedNamespaces[] = $this->buildFacade->getNamespaceFromFile($file)->getNamespace();
                $changedFiles[] = $file;
            } catch (Throwable) {
                // A file that does not parse selects nothing; the test run
                // itself will report the parse error if the file is loaded.
            }
        }

        return new ChangeSelection(
            $changedFiles,
            $this->affected->select($infos, $changedNamespaces, $this->commandFacade->getTestDirectories()),
        );
    }
}
