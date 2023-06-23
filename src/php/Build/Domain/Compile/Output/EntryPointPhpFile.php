<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile\Output;

use Phel\Config\PhelOutConfig;

final class EntryPointPhpFile implements EntryPointPhpFileInterface
{
    public function __construct(
        private PhelOutConfig $phelOutConfig,
        private NamespacePathTransformer $namespacePathTransformer,
        private string $appRootDir,
    ) {
    }

    public function createFile(): void
    {
        file_put_contents(
            $this->outMainPhpPath(),
            $this->outMainPhpContent(),
        );
    }

    private function outMainPhpPath(): string
    {
        return sprintf(
            '%s/%s',
            $this->appRootDir,
            $this->phelOutConfig->getMainPhpPath(),
        );
    }

    private function outMainPhpContent(): string
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
            $this->phelOutConfig->getMainPhelNamespace(),
        );
    }
}
