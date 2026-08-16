<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesDeprecationsTrait;
use PHPUnit\Framework\TestCase;

/**
 * The two facade methods Api, Build and Console use instead of importing the
 * compiler's `Domain` themselves (#3048). Both are one-liners over compiler
 * internals, and the point of each is the boundary rather than the behaviour,
 * so what is pinned is that going through the facade lands in the same place
 * the direct call did.
 */
final class CompilerFacadeBoundaryTest extends TestCase
{
    use CapturesDeprecationsTrait;

    private CompilerFacade $compilerFacade;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::initializeNew();
        $this->compilerFacade = new CompilerFacade();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingDeprecations();
    }

    public function test_the_empty_environment_matches_the_one_the_analyzer_builds(): void
    {
        $env = $this->compilerFacade->emptyNodeEnvironment();

        self::assertEquals(NodeEnvironment::empty(), $env);
        self::assertSame([], $env->getLocals());
    }

    public function test_the_empty_environment_is_usable_for_analysis(): void
    {
        $node = $this->compilerFacade->analyze(
            1,
            $this->compilerFacade->emptyNodeEnvironment()->withReturnContext(),
        );

        self::assertTrue($node->getEnv()->isContext(NodeEnvironment::CONTEXT_RETURN));
    }

    public function test_enabling_deprecation_warnings_reaches_the_detectors(): void
    {
        // The switch is what every detector reads, so flipping it through the
        // facade has to make one of them fire. This is the coverage that used
        // to sit on the CLI flag, which no longer touches the switch.
        DeprecationWarnings::disable();
        $this->captureDeprecationsWithoutEnabling();

        $this->compilerFacade->enableDeprecationWarnings();

        self::assertTrue(DeprecationWarnings::isEnabled());

        $symbol = Symbol::createForNamespace(null, 'phel\\core/map');
        $symbol->setStartLocation(new SourceLocation('/app/user.phel', 1, 1));
        BackslashSeparatorDeprecator::getInstance()->maybeWarn($symbol);

        self::assertCount(1, $this->capturedDeprecations());
    }

    /**
     * Installs the capture handler but leaves the switch exactly as the test
     * set it, so the facade call is the only thing that can turn it on.
     */
    private function captureDeprecationsWithoutEnabling(): void
    {
        $enabled = DeprecationWarnings::isEnabled();
        $this->startCapturingDeprecations();
        $enabled ? DeprecationWarnings::enable() : DeprecationWarnings::disable();
    }
}
