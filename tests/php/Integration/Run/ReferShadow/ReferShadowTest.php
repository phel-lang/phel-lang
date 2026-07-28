<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\ReferShadow;

use Phel;
use Phel\Build\BuildFacade;
use PhelTest\Support\CapturesCompilerWarningsTrait;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end check that a namespace's own definition beats a `:refer` of the
 * same name (#2897), through the full pipeline
 * (lexer → parser → reader → analyzer → emitter → eval).
 */
final class ReferShadowTest extends TestCase
{
    use CapturesCompilerWarningsTrait;
    use RemoveDirTrait;

    /**
     * The warning is raised while analysing, so a warm build cache would skip
     * it and the assertions below would pass against whatever the last run
     * compiled rather than against the current compiler.
     */
    protected function setUp(): void
    {
        $this->removeDir(__DIR__ . '/.phel');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_own_definition_beats_a_refer_end_to_end(): void
    {
        $this->startCapturingCompilerWarnings();
        $this->compileFixtures();

        // The bare name has to call the definition the reader can see in the
        // file; before #2897 it silently called `refershadow.lib/greet`.
        self::assertSame('mine', Phel::getDefinition('refershadow.main', 'bare'));
        self::assertSame('mine', Phel::getDefinition('refershadow.main', 'qualified'));

        // Worse variant of the same bug: the recursive self-call reached the
        // referred `countdown` and returned "lib-0" instead of recursing.
        self::assertSame('base', Phel::getDefinition('refershadow.main', 'recursed'));

        // A referred name the namespace never redefines still resolves.
        self::assertSame('from lib', Phel::getDefinition('refershadow.main', 'not-redefined'));

        $captured = $this->capturedCompilerWarnings();
        self::assertCount(2, $captured, 'one warning per shadowed refer, none for `untouched`');
        self::assertStringContainsString("greet already refers to: #'refershadow.lib/greet", $captured[0]);
        self::assertStringContainsString("being replaced by: #'refershadow.main/greet", $captured[0]);
        self::assertStringContainsString("countdown already refers to: #'refershadow.lib/countdown", $captured[1]);

        $this->stopCapturingCompilerWarnings();
    }

    private function compileFixtures(): void
    {
        $fixtures = __DIR__ . '/Fixtures';
        Phel::bootstrap(__DIR__);
        Phel::addDefinition('phel.repl', 'src-dirs', [$fixtures]);

        $buildFacade = new BuildFacade();

        // Compile phel\core first so the fixtures can rely on `defn` and friends.
        $buildFacade->compileFile(
            __DIR__ . '/../../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );

        $mainFile = $fixtures . '/refershadow/main.phel';
        $mainNs = $buildFacade->getNamespaceFromFile($mainFile)->getNamespace();

        foreach ($buildFacade->getDependenciesForNamespace([$fixtures], [$mainNs]) as $info) {
            $buildFacade->evalFile($info->getFile());
        }
    }
}
