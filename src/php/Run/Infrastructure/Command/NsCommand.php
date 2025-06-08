<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\RunFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function implode;
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
            ->addArgument('inspect', InputArgument::OPTIONAL, 'Namespace to inspect')
            ->addOption('simple', 's', InputOption::VALUE_NONE, 'Display only namespace names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nsToInspect = $input->getArgument('inspect');
        $isSimple = $input->getOption('simple') === true;

        if ($nsToInspect === null) {
            return $this->listLoadedNamespaces($output, $isSimple);
        }

        return $this->displayNamespaceDependencies($nsToInspect, $output, $isSimple);
    }

    private function listLoadedNamespaces(OutputInterface $output, bool $simple): int
    {
        $loadedNamespaces = $this->getFacade()->getLoadedNamespaces();

        if (empty($loadedNamespaces)) {
            $output->writeln('<comment>No namespaces loaded.</comment>');
            return self::SUCCESS;
        }

        foreach ($loadedNamespaces as $i => $ns) {
            if ($simple) {
                $output->writeln($ns->getNamespace());
                continue;
            }

            $this->renderNamespaceInfo($output, $i, $ns);
        }

        return self::SUCCESS;
    }

    private function displayNamespaceDependencies(string $ns, OutputInterface $output, bool $simple): int
    {
        $nsInfoList = $this->getNamespaceInfoList($ns);

        if ($simple) {
            foreach ($nsInfoList as $info) {
                $output->writeln($info->getNamespace());
            }

            return self::SUCCESS;
        }

        $output->writeln(sprintf('Dependencies for namespace: %s', $ns));

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
        $dependencies = $info->getDependencies();
        $depsCount = count($dependencies);
        $depsString = $depsCount === 0 ? '-' : implode(', ', $dependencies);

        $output->writeln(sprintf('%d) Namespace: %s', $index + 1, $info->getNamespace()));
        $output->writeln(sprintf('   File: %s', $info->getFile()));
        $output->writeln(sprintf('   Dependencies (%d): %s', $depsCount, $depsString));
    }
}
