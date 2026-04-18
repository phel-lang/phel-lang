<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

use Phel\Config\ProjectLayout;

final class ProjectTemplateGenerator
{
    public function generateConfig(string $namespace, ProjectLayout $layout): string
    {
        return match ($layout) {
            ProjectLayout::Flat => <<<PHP
<?php

use Phel\\Config\\PhelConfig;

return PhelConfig::forProject(mainNamespace: '{$namespace}');

PHP,
            ProjectLayout::Nested => <<<PHP
<?php

use Phel\\Config\\PhelConfig;
use Phel\\Config\\ProjectLayout;

return PhelConfig::forProject(mainNamespace: '{$namespace}', layout: ProjectLayout::Nested);

PHP,
            ProjectLayout::Root => <<<PHP
<?php

use Phel\\Config\\PhelConfig;
use Phel\\Config\\ProjectLayout;

return PhelConfig::forProject(mainNamespace: '{$namespace}', layout: ProjectLayout::Root);

PHP,
        };
    }

    public function generateMainFile(string $namespace): string
    {
        return <<<PHEL
(ns {$namespace})

(defn greet [name]
  (str "Hello, " name "!"))

(defn main []
  (println (greet "Phel")))

(main)

PHEL;
    }

    public function generateTestFile(string $namespace): string
    {
        $testNamespace = $namespace . '-test';

        return <<<PHEL
(ns {$testNamespace}
  (:require phel\\test :refer [deftest is])
  (:require {$namespace} :refer [greet]))

(deftest test-greet
  (is (= "Hello, Phel!" (greet "Phel")))
  (is (= "Hello, Alice!" (greet "Alice"))))

PHEL;
    }

    public function generateGitignore(ProjectLayout $layout = ProjectLayout::Flat): string
    {
        if ($layout === ProjectLayout::Root) {
            return <<<'GITIGNORE'
/vendor/
/out/
*.phar
.phpunit.result.cache
phel-config-local.php

GITIGNORE;
        }

        return <<<'GITIGNORE'
/vendor/
/out/
/src/PhelGenerated/
*.phar
.phpunit.result.cache
phel-config-local.php

GITIGNORE;
    }
}
