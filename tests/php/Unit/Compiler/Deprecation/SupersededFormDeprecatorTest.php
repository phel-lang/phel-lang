<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Deprecation;

use Phel;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Domain\Deprecation\SupersededFormDeprecator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PhelTest\Support\CapturesDeprecationsTrait;
use PHPUnit\Framework\TestCase;

use function dirname;

final class SupersededFormDeprecatorTest extends TestCase
{
    use CapturesDeprecationsTrait;

    protected function setUp(): void
    {
        $this->startCapturingDeprecations();
    }

    protected function tearDown(): void
    {
        $this->stopCapturingDeprecations();
    }

    public function test_emits_for_php_new(): void
    {
        new SupersededFormDeprecator()->maybeWarn($this->headed('php/new', '/app/user.phel'));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString('"php/new"', $captured[0]);
        self::assertStringContainsString('(new \Foo arg)', $captured[0]);
        self::assertStringContainsString('/app/user.phel:7:3', $captured[0]);
    }

    public function test_emits_for_php_object_call(): void
    {
        new SupersededFormDeprecator()->maybeWarn($this->headed('php/->', '/app/user.phel'));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString('(.method obj arg)', $captured[0]);
    }

    public function test_emits_for_php_static_call(): void
    {
        new SupersededFormDeprecator()->maybeWarn($this->headed('php/::', '/app/user.phel'));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString('(\Foo/method arg)', $captured[0]);
    }

    public function test_emits_for_set_var(): void
    {
        new SupersededFormDeprecator()->maybeWarn($this->headed('set-var', '/app/user.phel'));

        $captured = $this->capturedDeprecations();
        self::assertCount(1, $captured);
        self::assertStringContainsString('alter-var-root', $captured[0]);
    }

    /**
     * Each occurrence is a separate edit, so unlike a deprecated *definition*
     * these are not deduplicated per file.
     */
    public function test_reports_every_occurrence_in_one_file(): void
    {
        $deprecator = new SupersededFormDeprecator();
        $deprecator->maybeWarn($this->headed('php/new', '/app/user.phel', line: 7));
        $deprecator->maybeWarn($this->headed('php/new', '/app/user.phel', line: 19));

        self::assertCount(2, $this->capturedDeprecations());
    }

    public function test_no_warning_for_a_php_form_that_stays(): void
    {
        $deprecator = new SupersededFormDeprecator();
        foreach (['php/aget', 'php/aset', 'php/oset', 'php/ref', 'php/callable', 'new'] as $keeper) {
            $deprecator->maybeWarn($this->headed($keeper, '/app/user.phel'));
        }

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_no_warning_for_a_plain_function_call(): void
    {
        new SupersededFormDeprecator()->maybeWarn($this->headed('map', '/app/user.phel'));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_no_warning_when_the_head_is_not_a_symbol(): void
    {
        new SupersededFormDeprecator()->maybeWarn(Phel::list([1, 2]));

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_stays_silent_when_warnings_are_disabled(): void
    {
        DeprecationWarnings::disable();

        new SupersededFormDeprecator()->maybeWarn($this->headed('php/new', '/app/user.phel'));

        self::assertSame([], $this->capturedDeprecations());
    }

    /**
     * A user cannot act on a `php/->` inside phel's own stdlib, so it must not
     * reach their output.
     */
    public function test_suppresses_warnings_from_phel_stdlib_sources(): void
    {
        new SupersededFormDeprecator()->maybeWarn(
            $this->headed('php/->', dirname(__DIR__, 5) . '/src/phel/core/protocols.phel'),
        );

        self::assertSame([], $this->capturedDeprecations());
    }

    public function test_reports_the_names_it_deprecates(): void
    {
        self::assertSame(
            ['php/new', 'php/->', 'php/::', 'set-var'],
            SupersededFormDeprecator::supersededFormNames(),
        );
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private function headed(string $name, string $file, int $line = 7): PersistentListInterface
    {
        $head = Symbol::create($name);
        $head->setStartLocation(new SourceLocation($file, $line, 3));

        return Phel::list([$head]);
    }
}
