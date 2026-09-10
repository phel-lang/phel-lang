<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use Throwable;

use function implode;
use function sprintf;
use function str_repeat;

/**
 * A special form given too few arguments must say so itself. It used to read
 * past the end of its own list first, so the user got `[PHEL403] Index out of
 * bounds` raised inside `PersistentList`, or a raw `AssertionError`: a runtime
 * error code for a compile-time problem, with no snippet, no caret, and an `at`
 * line pointing inside Phel (#3297).
 *
 * The analyzer's own registry drives the cases, so a special form added without
 * an arity check fails here rather than reaching a user. Two instances of this
 * were fixed one at a time in #3266; fixing them singly is why the class kept
 * coming back.
 */
final class SpecialFormArityTest extends AbstractCompilerRuntimeTestCase
{
    /** How many leading arguments to try before moving on to the next form. */
    private const int MAX_ARGS = 2;

    public function test_no_special_form_fails_with_anything_but_an_analyzer_error(): void
    {
        $offenders = [];

        foreach ($this->specialFormNames() as $name) {
            for ($count = 0; $count <= self::MAX_ARGS; ++$count) {
                $source = sprintf('(%s%s)', $name, str_repeat(' 1', $count));
                $failure = $this->analyzeAndCatch($source);

                if ($failure !== null) {
                    $offenders[] = sprintf('%s -> %s', $source, $failure);
                }
            }
        }

        self::assertSame([], $offenders, sprintf(
            "These forms failed with something other than an AnalyzerException:\n%s\n\n"
            . 'Check the argument count before reading an argument.',
            implode("\n", $offenders),
        ));
    }

    /**
     * @return string|null the offending class and message, or null when the
     *                     form analysed cleanly or was properly rejected
     */
    private function analyzeAndCatch(string $source): ?string
    {
        GlobalEnvironmentSingleton::initializeNew();

        try {
            foreach ($this->compilerFacade->readFormsBestEffort($source, 'arity.phel') as $form) {
                $this->compilerFacade->analyze($form, NodeEnvironment::empty());
            }
        } catch (AnalyzerException) {
            // The analyzer rejected the form, which is the whole point. Some
            // forms are also legal with no arguments, so analysing cleanly is
            // just as acceptable an outcome.
            return null;
        } catch (Throwable $t) {
            return sprintf('%s: %s', $t::class, $t->getMessage());
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function specialFormNames(): array
    {
        return new AnalyzePersistentList(
            $this->createStub(AnalyzerInterface::class),
            assertsEnabled: true,
        )->specialFormNames();
    }
}
