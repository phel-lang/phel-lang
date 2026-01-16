<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

use Phel\Config\ProjectLayout;

final class ProjectTemplateGenerator
{
    public function generateConfig(string $namespace, ProjectLayout $layout): string
    {
        if ($layout === ProjectLayout::Flat) {
            return <<<'PHP'
<?php return \Phel\Config\PhelConfig::forProject()->useFlatLayout();

PHP;
        }

        return <<<'PHP'
<?php return \Phel\Config\PhelConfig::forProject();

PHP;
    }

    public function generateCoreFile(string $namespace): string
    {
        return <<<PHEL
(ns {$namespace})

(defn main []
  (println "Hello from Phel!"))

(main)

PHEL;
    }

    public function generateGitignore(): string
    {
        return <<<GITIGNORE
/vendor/
/out/
/src/PhelGenerated/
*.phar
.phpunit.result.cache
phel-config-local.php

GITIGNORE;
    }
}
