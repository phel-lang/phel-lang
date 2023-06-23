<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use Gacela\Framework\Gacela;
use Phel\Build\Domain\Compile\Output\NamespacePathTransformer;
use Phel\Config\PhelOutConfig;

final class MainPhpEntryPointFile
{
    public function __construct(
        private PhelOutConfig $phelOutConfig,
        private NamespacePathTransformer $namespacePathTransformer,
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
        $outputMainPhelPath = $this->namespacePathTransformer->getOutputMainNamespacePath(
            $this->phelOutConfig->getMainPhelNamespace(),
        );

        $outPhpContent = str_replace(
            '{{OUTPUT_MAIN_PHEL_PATH}}',
            $outputMainPhelPath,
            $template,
        );

        $outPhpPath = sprintf(
            '%s/%s',
            Gacela::rootDir(),
            $this->phelOutConfig->getMainPhpPath(),
        );

        file_put_contents($outPhpPath, $outPhpContent);
    }
}
