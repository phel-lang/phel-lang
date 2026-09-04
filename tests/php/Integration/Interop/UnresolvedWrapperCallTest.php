<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop;

use Phel\Build\BuildFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Interop\ExportedDefinitionNotFoundException;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function tempnam;

/**
 * A PHP host calling an exported function is the one place where a Phel error
 * reaches somebody who may never have written Phel, so the two ways resolution
 * fails have to name themselves rather than surface as `null is not callable`.
 */
final class UnresolvedWrapperCallTest extends TestCase
{
    private UnresolvedWrapperFixture $wrapper;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);

        GlobalEnvironmentSingleton::initializeNew();

        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        $this->wrapper = new UnresolvedWrapperFixture();
    }

    public function test_it_names_the_namespace_the_host_never_loaded(): void
    {
        $this->expectException(ExportedDefinitionNotFoundException::class);
        $this->expectExceptionMessage('the namespace "my-app.billing" is not loaded in this process');

        $this->wrapper->intoUnloadedNamespace();
    }

    public function test_it_points_at_a_stale_wrapper_when_the_namespace_is_loaded(): void
    {
        $this->expectException(ExportedDefinitionNotFoundException::class);
        $this->expectExceptionMessage('re-run "phel export"');

        $this->wrapper->intoMissingDefinition();
    }
}
