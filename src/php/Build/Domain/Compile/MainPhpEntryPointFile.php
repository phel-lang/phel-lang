<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Phel\Build\Domain\Compile\Output\NamespacePathTransformer;
use Phel\Config\PhelOutConfig;

final class MainPhpEntryPointFile
{
    public function __construct(
        private PhelOutConfig $phelOutConfig,
        private NamespacePathTransformer $namespacePathTransformer,
        private string $appRootDir
    ) {
    }

    public function createFile(): void
    {
        $template = <<<'TXT'
<?php declare(strict_types=1);

require_once dirname(__DIR__) . "/vendor/autoload.php";

$compiledFile = __DIR__ . "/{{OUTPUT_MAIN_PHEL_PATH}}.php";

require_once $compiledFile;
TXT;
        $outPhpContent = str_replace(
            '{{OUTPUT_MAIN_PHEL_PATH}}',
            $this->outputMainPhelPath(),
            $template,
        );

        $outPhpPath = sprintf(
            '%s/%s',
            $this->appRootDir,
            $this->phelOutConfig->getMainPhpPath(),
        );

        file_put_contents($outPhpPath, $outPhpContent);
    }

    private function outputMainPhelPath(): string
    {
        return $this->namespacePathTransformer->getOutputMainNamespacePath(
            $this->phelOutConfig->getMainPhelNamespace(),
        );
    }
}
