<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

use Phel\Config\ProjectLayout;

final class ProjectTemplateGenerator
{
    public function generateConfig(string $namespace, ProjectLayout $layout): string
    {
        $layoutCase = $layout->name;

        return <<<PHP
<?php

declare(strict_types=1);

use Phel\\Config\\PhelConfig;
use Phel\\Config\\ProjectLayout;

// See https://phel-lang.org/documentation/configuration/
// for every option, caching flags, and precedence. Run `phel config` to print
// the effective configuration. A few common tweaks:
//     ->withWarnDeprecations(true)
//     ->withIgnoreWhenBuilding(['src/scratch.phel'])
// withOptimizationLevel(2) inlines core arithmetic/bit fns and elides their
// nil-guards; drop to 0 if you prefer those runtime checks during development.
return PhelConfig::forProject(ProjectLayout::{$layoutCase})
    ->withMainPhelNamespace('{$namespace}')
    ->withOptimizationLevel(2);

PHP;
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
