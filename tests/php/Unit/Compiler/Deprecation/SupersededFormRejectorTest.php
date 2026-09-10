<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Deprecation;

use Phel;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Deprecation\SupersededFormRejector;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function sprintf;

/**
 * The four forms are rejected as source from 1.0.0 and kept as the compiler's
 * own target. What separates the two is where the head was written, so most of
 * these cases are about that and not about the message.
 */
final class SupersededFormRejectorTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function supersededProvider(): iterable
    {
        yield 'php/new' => ['php/new', '(new \Foo arg)'];
        yield 'php/->' => ['php/->', '(.method obj arg)'];
        yield 'php/::' => ['php/::', '(\Foo/method arg)'];
        yield 'set-var' => ['set-var', 'alter-var-root'];
    }

    #[DataProvider('supersededProvider')]
    public function test_a_written_form_is_rejected_and_names_its_replacement(string $name, string $replacement): void
    {
        try {
            new SupersededFormRejector()->rejectIfWritten($this->headed($name, '/app/user.phel'));
        } catch (AnalyzerException $analyzerException) {
            self::assertSame(ErrorCode::SUPERSEDED_FORM, $analyzerException->getErrorCode());
            self::assertStringContainsString(sprintf('"%s"', $name), $analyzerException->getMessage());
            self::assertStringContainsString($replacement, $analyzerException->getMessage());
            return;
        }

        self::fail($name . ' was accepted');
    }

    public function test_a_form_that_stays_is_accepted(): void
    {
        $rejector = new SupersededFormRejector();

        foreach (['php/aget', 'php/aset', 'php/oset', 'php/ref', 'php/callable', 'new', 'map'] as $keeper) {
            $rejector->rejectIfWritten($this->headed($keeper, '/app/user.phel'));
        }

        $this->expectNotToPerformAssertions();
    }

    public function test_a_head_that_is_not_a_symbol_is_accepted(): void
    {
        new SupersededFormRejector()->rejectIfWritten(Phel::list([1, 2]));

        $this->expectNotToPerformAssertions();
    }

    /**
     * An unlocated head is one the analyzer synthesized, never one anybody
     * wrote: `QualifiedMemberExpander` turns `\C/CONST` into a `php/::` form
     * this way, and it has to keep compiling.
     */
    public function test_a_synthesized_head_is_accepted(): void
    {
        new SupersededFormRejector()->rejectIfWritten(Phel::list([Symbol::create('php/::')]));

        $this->expectNotToPerformAssertions();
    }

    /**
     * `binding` builds `(set-var v e)` and `set!` builds a `php/::`. Both are
     * written in `src/phel` and land at the caller's location, so the origin
     * is what tells them apart from a form the caller wrote.
     */
    public function test_a_form_expanded_from_a_stdlib_macro_is_accepted(): void
    {
        new SupersededFormRejector()->rejectIfWritten(
            $this->headed('set-var', '/app/user.phel', origin: dirname(__DIR__, 5) . '/src/phel/core/io.phel'),
        );

        $this->expectNotToPerformAssertions();
    }

    /**
     * A macro of the user's own that emits one of these is theirs to fix.
     */
    public function test_a_form_expanded_from_a_user_macro_is_rejected(): void
    {
        $this->expectException(AnalyzerException::class);

        new SupersededFormRejector()->rejectIfWritten(
            $this->headed('php/new', '/app/caller.phel', origin: '/app/macros.phel'),
        );
    }

    /**
     * A form written in the stdlib itself: `set!` on a static property expands
     * through a `php/::` written down in `src/phel/core/interop.phel`.
     */
    public function test_a_form_written_in_the_stdlib_is_accepted(): void
    {
        new SupersededFormRejector()->rejectIfWritten(
            $this->headed('php/->', dirname(__DIR__, 5) . '/src/phel/core/protocols.phel'),
        );

        $this->expectNotToPerformAssertions();
    }

    public function test_reports_the_names_it_rejects(): void
    {
        self::assertSame(
            ['php/new', 'php/->', 'php/::', 'set-var'],
            SupersededFormRejector::supersededFormNames(),
        );
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private function headed(string $name, string $file, int $line = 7, ?string $origin = null): PersistentListInterface
    {
        $location = new SourceLocation($file, $line, 3);
        if ($origin !== null) {
            $location = $location->withExpansionOrigin(new SourceLocation($origin, 1, 0));
        }

        $head = Symbol::create($name);
        $head->setStartLocation($location);

        return Phel::list([$head]);
    }
}
