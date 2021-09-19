<?php

declare(strict_types=1);

namespace Phel\Command\Runtime;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RuntimeCommand extends Command
{
    private const COMMAND_NAME = 'runtime';
    private const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private string $applicationRootDir;

    private ConfigNormalizer $configNormalizer;

    private RuntimeFileGenerator $runtimeFileGenerator;

    public function __construct(
        string $applicationRootDir,
        ConfigNormalizer $configNormalizer,
        RuntimeFileGenerator $runtimeFileGenerator
    ) {
        parent::__construct(self::COMMAND_NAME);

        $this->applicationRootDir = $applicationRootDir;
        $this->configNormalizer = $configNormalizer;
        $this->runtimeFileGenerator = $runtimeFileGenerator;
    }

    protected function configure(): void
    {
        $this->setDescription('Generates the PhelRuntime file in vendor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loaderConfig = [
            $this->loadRootConfig(),
            $this->loadVendorConfig(),
        ];

        /** @var array<string, list<string>> $flatConfig [ns => [path1, path2, ...]] */
        $flatConfig = array_merge(...$loaderConfig);

        file_put_contents(
            $this->applicationRootDir . 'vendor/PhelRuntime.php',
            $this->runtimeFileGenerator->generate($flatConfig)
        );

        $output->writeln('<info>PhelRuntime created/updated successfully!</info>');

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadRootConfig(): array
    {
        $rootPhelConfigPath = $this->applicationRootDir . self::PHEL_CONFIG_FILE_NAME;
        $result = [];

        if (is_file($rootPhelConfigPath)) {
            $pathPrefix = '/..';
            $rootPhelConfig = require $rootPhelConfigPath;
            $result[] = $this->configNormalizer->normalize($rootPhelConfig['loader'] ?? [], $pathPrefix);
            $result[] = $this->configNormalizer->normalize($rootPhelConfig['loader-dev'] ?? [], $pathPrefix);
        }

        return array_merge(...$result);
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadVendorConfig(): array
    {
        $pattern = $this->applicationRootDir . 'vendor/*/*/' . self::PHEL_CONFIG_FILE_NAME;
        $result = [];

        foreach (glob($pattern) as $phelConfigPath) {
            $pathPrefix = '/' . basename(dirname($phelConfigPath));
            /** @psalm-suppress UnresolvableInclude */
            $configLoader = (require $phelConfigPath)['loader'] ?? [];
            $result[] = $this->configNormalizer->normalize($configLoader, $pathPrefix);
        }

        return array_merge(...$result);
    }
}
