<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile\Output;

use Phel\Config\PhelBuildConfig;
use Throwable;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function md5;
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
        $filepath = $this->buildMainPhpPath();
        $content = $this->buildMainPhpContent();

        if ($this->contentHasChanged($filepath, $content)) {
            file_put_contents($filepath, $content);
        }
    }

    /**
     * Checks if file content differs from the new content using MD5 hashing.
     */
    private function contentHasChanged(string $filepath, string $newContent): bool
    {
        if (!file_exists($filepath)) {
            return true;
        }

        try {
            $existingContent = file_get_contents($filepath);
            return $existingContent === false || md5($existingContent) !== md5($newContent);
        } catch (Throwable) {
            return true;
        }
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
