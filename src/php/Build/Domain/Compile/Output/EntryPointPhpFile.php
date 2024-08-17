<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile\Output;

use Phel\Config\PhelBuildConfig;

use function sprintf;

final readonly class EntryPointPhpFile implements EntryPointPhpFileInterface
{
    public function __construct(
        private PhelBuildConfig $phelBuildConfig,
        private NamespacePathTransformer $namespacePathTransformer,
        private string $appRootDir,
    ) {
    }

    public function createFile(): void
    {
        file_put_contents(
            $this->buildMainPhpPath(),
            $this->buildMainPhpContent(),
        );
    }

    private function buildMainPhpPath(): string
    {
        return sprintf(
            '%s/%s',
            $this->appRootDir,
            $this->phelBuildConfig->getMainPhpPath(),
        );
    }

    private function buildMainPhpContent(): string
    {
        $template = <<<'TXT'
<?php declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";

$compiledFile = __DIR__ . "/{{OUTPUT_MAIN_PHEL_PATH}}.php";

require_once $compiledFile;
TXT;
        return str_replace(
            '{{OUTPUT_MAIN_PHEL_PATH}}',
            $this->outputMainPhelPath(),
            $template,
        );
    }

    private function outputMainPhelPath(): string
    {
        return $this->namespacePathTransformer->transform(
            $this->phelBuildConfig->getMainPhelNamespace(),
        );
    }
}
