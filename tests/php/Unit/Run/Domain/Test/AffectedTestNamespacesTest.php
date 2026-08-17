<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Test;

use Phel\Run\Domain\Test\AffectedTestNamespaces;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

final class AffectedTestNamespacesTest extends TestCase
{
    /** @var list<NamespaceInformation> */
    private array $infos;

    protected function setUp(): void
    {
        $this->infos = [
            new NamespaceInformation('/p/vendor/phel/src/phel/core.phel', 'phel.core', []),
            new NamespaceInformation('/p/src/app/util.phel', 'app.util', ['phel.core']),
            new NamespaceInformation('/p/src/app/calc.phel', 'app.calc', ['phel.core', 'app.util']),
            new NamespaceInformation('/p/src/app/web.phel', 'app.web', ['phel.core', 'app.calc']),
            new NamespaceInformation('/p/src/app/lonely.phel', 'app.lonely', ['phel.core']),
            new NamespaceInformation('/p/tests/app/util_test.phel', 'app.util-test', ['phel.test', 'app.util']),
            new NamespaceInformation('/p/tests/app/calc_test.phel', 'app.calc-test', ['phel.test', 'app.calc']),
            new NamespaceInformation('/p/tests/app/web_test.phel', 'app.web-test', ['phel.test', 'app.web']),
            new NamespaceInformation('/p/tests/app/other_test.phel', 'app.other-test', ['phel.test']),
        ];
    }

    public function test_a_changed_source_selects_every_test_namespace_that_transitively_requires_it(): void
    {
        $affected = new AffectedTestNamespaces()->select($this->infos, ['app.util'], ['/p/tests']);

        self::assertSame(['app.util-test', 'app.calc-test', 'app.web-test'], $affected);
    }

    public function test_a_changed_test_file_selects_only_itself(): void
    {
        $affected = new AffectedTestNamespaces()->select($this->infos, ['app.other-test'], ['/p/tests']);

        self::assertSame(['app.other-test'], $affected);
    }

    public function test_a_changed_leaf_nobody_requires_selects_nothing(): void
    {
        self::assertSame([], new AffectedTestNamespaces()->select($this->infos, ['app.lonely'], ['/p/tests']));
    }

    public function test_a_namespace_the_scan_does_not_know_is_ignored(): void
    {
        $affected = new AffectedTestNamespaces()->select($this->infos, ['elsewhere.x', 'app.web'], ['/p/tests']);

        self::assertSame(['app.web-test'], $affected);
    }

    public function test_the_result_is_deduplicated_and_in_scan_order(): void
    {
        $affected = new AffectedTestNamespaces()->select(
            $this->infos,
            ['app.calc', 'app.util', 'app.calc-test'],
            ['/p/tests'],
        );

        self::assertSame(['app.util-test', 'app.calc-test', 'app.web-test'], $affected);
    }
}
