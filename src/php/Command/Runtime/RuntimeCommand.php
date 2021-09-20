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
    public const DEFAULT_VENDOR_DIR = 'vendor';

    private string $applicationRootDir;

    private ConfigNormalizer $configNormalizer;

    private RuntimeFileGenerator $runtimeFileGenerator;

    /**
     * @var null|array{
     *     loader:array|null,
     *     loader-dev:array|null,
     *     vendor-dir:string|null,
     * }
     */
    private ?array $rootPhelConfig = null;

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
            $this->getVendorDir() . '/PhelRuntime.php',
            $this->runtimeFileGenerator->generate($flatConfig)
        );

        $output->writeln('<info>PhelRuntime created/updated successfully!</info>');

        return self::SUCCESS;
    }

    /**
     * Uses 'loader' and 'loader-dev' from the phel config.
     *
     * @return array<string, list<string>>
     */
    private function loadRootConfig(): array
    {
        $result = [];
        $pathPrefix = '/..';
        $config = $this->getRootPhelConfig();

        $result[] = $this->configNormalizer->normalize($config['loader'] ?? [], $pathPrefix);
        $result[] = $this->configNormalizer->normalize($config['loader-dev'] ?? [], $pathPrefix);

        return array_merge(...$result);
    }

    /**
     * Uses only 'loader' from the phel config.
     *
     * @return array<string, list<string>>
     */
    private function loadVendorConfig(): array
    {
        $pattern = $this->getVendorDir() . '/*/*/' . self::PHEL_CONFIG_FILE_NAME;

        $result = [];

        foreach (glob($pattern) as $phelConfigPath) {
            $pathPrefix = '/' . basename(dirname($phelConfigPath));
            /** @psalm-suppress UnresolvableInclude */
            $config = (require $phelConfigPath)['loader'] ?? [];
            $result[] = $this->configNormalizer->normalize($config, $pathPrefix);
        }

        return array_merge(...$result);
    }

    private function getVendorDir(): string
    {
        $vendorDir = $this->getRootPhelConfig()['vendor-dir'] ?? self::DEFAULT_VENDOR_DIR;

        return $this->applicationRootDir . $vendorDir;
    }

    /**
     * @return array{
     *     loader:array|null,
     *     loader-dev:array|null,
     *     vendor-dir:string|null,
     * }
     */
    private function getRootPhelConfig(): array
    {
        $rootPhelConfigPath = $this->applicationRootDir . self::PHEL_CONFIG_FILE_NAME;

        if ($this->rootPhelConfig === null) {
            $this->rootPhelConfig = is_file($rootPhelConfigPath)
                ? require $rootPhelConfigPath
                : [];
        }

        return $this->rootPhelConfig;
    }
}
