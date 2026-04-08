<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\RequireVector;

use Phel;
use Phel\Build\BuildFacade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end check that the Clojure-style vector form of `:require`
 * (added in #1183) loads dependent namespaces, resolves `:as` aliases,
 * and brings `:refer`-ed symbols into the requiring namespace through
 * the full pipeline (lexer → parser → reader → analyzer → emitter → eval).
 */
final class RequireVectorTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_clojure_style_vector_require_resolves_aliases_and_refers_end_to_end(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        Phel::bootstrap(__DIR__);

        Phel::addDefinition('phel\\repl', 'src-dirs', [$fixtures]);

        $buildFacade = new BuildFacade();

        // Compile phel\core first so user code can rely on `defn` and friends.
        $buildFacade->compileFile(
            __DIR__ . '/../../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );

        $mainFile = $fixtures . '/requirevector/main.phel';
        $mainNs = $buildFacade->getNamespaceFromFile($mainFile)->getNamespace();

        // Resolve and evaluate every dependency in topological order so that
        // `:require [requirevector\lib-one ...]` can find both lib namespaces.
        $deps = $buildFacade->getDependenciesForNamespace([$fixtures], [$mainNs]);
        foreach ($deps as $info) {
            $buildFacade->evalFile($info->getFile());
        }

        // `:refer [one-greeting]` pulled the function into the main ns and the
        // call resolved successfully — proves both vector parsing AND :refer.
        self::assertSame(
            'hello from one',
            Phel::getDefinition('requirevector\\main', 'greeting'),
        );

        // `:as two` aliased lib-two so `(two/two-shout ...)` resolved correctly
        // — proves the :as alias was registered through the vector form.
        self::assertSame(
            'shout!',
            Phel::getDefinition('requirevector\\main', 'shouted'),
        );
    }
}
