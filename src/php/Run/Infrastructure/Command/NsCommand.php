<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\RunFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

/**
 * @method RunFacade getFacade()
 */
class NsCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this
            ->setName('ns')
            ->setAliases(['loaded-ns'])
            ->setDescription('Display all loaded namespaces or inspect a namespace')
            ->addArgument('inspect', InputArgument::OPTIONAL, 'Namespace to inspect');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nsToInspect = $input->getArgument('inspect');

        if ($nsToInspect === null) {
            return $this->listLoadedNamespaces($output);
        }

        return $this->displayNamespaceDependencies($nsToInspect, $output);
    }

    private function displayNamespaceDependencies(string $ns, OutputInterface $output): int
    {
        $output->writeln(sprintf('Dependencies for namespace: %s', $ns));

        $nsInfoList = $this->getNamespaceInfoList($ns);

        foreach ($nsInfoList as $index => $info) {
            $this->renderNamespaceInfo($output, $index, $info);
        }

        return self::SUCCESS;
    }

    private function getNamespaceInfoList(string $ns): array
    {
        return $this->getFacade()->getDependenciesForNamespace(
            $this->getFacade()->getAllPhelDirectories(),
            [$ns, 'phel\\core'],
        );
    }

    private function renderNamespaceInfo(OutputInterface $output, int $index, NamespaceInformation $info): void
    {
        $ns = $info->getNamespace();
        $file = $info->getFile();
        $dependencies = $info->getDependencies();
        $depsCount = count($dependencies);
        $depsString = $depsCount === 0 ? '-' : implode(', ', $dependencies);

        $lastModified = file_exists($file) ? date('Y-m-d H:i:s', filemtime($file)) : 'Unknown';
        $linesOfCode = file_exists($file) ? count(file($file)) : 'Unknown';

        $output->writeln(sprintf('  %d) Namespace: %s', $index + 1, $ns));
        $output->writeln(sprintf('     File: %s', $file));
        $output->writeln(sprintf('     Dependencies (%d): %s', $depsCount, $depsString));
        $output->writeln(sprintf('     Last Modified: %s', $lastModified));
        $output->writeln(sprintf('     Lines of Code: %s', $linesOfCode));
    }

    private function listLoadedNamespaces(OutputInterface $output): int
    {
        foreach ($this->getFacade()->getLoadedNamespaces() as $ns) {
            $output->writeln($ns);
        }

        return self::SUCCESS;
    }
}
