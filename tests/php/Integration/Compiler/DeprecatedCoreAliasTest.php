<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;
use function sys_get_temp_dir;
use function tempnam;

use const E_USER_DEPRECATED;

/**
 * `to-php-array` is the first `phel.core` definition to carry `:deprecated`
 * metadata, so this pins that the generic definition-level channel actually
 * reaches a stdlib alias: the notice fires at a *user* call site, names the
 * replacement, and stays silent when the flag is off (#3076).
 *
 * The mechanism itself is covered by `DeprecatedDefinitionWarnerTest`; what is
 * specific here is that the deprecation is announced from the standard library,
 * whose own sources are suppressed.
 */
final class DeprecatedCoreAliasTest extends TestCase
{
    private static GlobalEnvironmentInterface $globalEnv;

    private CompilerFacade $compilerFacade;

    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        $globalEnv = GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
        self::$globalEnv = $globalEnv;
    }

    protected function setUp(): void
    {
        $this->compilerFacade = new CompilerFacade();
        self::$globalEnv->setNs('user');
        Symbol::resetGen();
        DeprecationWarnings::reset();
        DeprecationWarnings::enable();
    }

    protected function tearDown(): void
    {
        DeprecationWarnings::reset();
    }

    public function test_calling_the_deprecated_alias_names_its_replacement(): void
    {
        $warnings = $this->compileCapturingDeprecations('(to-php-array [1 2])');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('phel.core/to-php-array', $warnings[0]);
        self::assertStringContainsString("Use 'to-array' instead", $warnings[0]);
    }

    public function test_the_replacement_does_not_warn(): void
    {
        self::assertSame([], $this->compileCapturingDeprecations('(to-array [1 2])'));
    }

    public function test_nothing_is_reported_when_warnings_are_disabled(): void
    {
        DeprecationWarnings::disable();

        self::assertSame([], $this->compileCapturingDeprecations('(to-php-array [1 2])'));
    }

    /**
     * @return list<string>
     */
    private function compileCapturingDeprecations(string $phelCode): array
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->compilerFacade
                ->compile($phelCode, new CompileOptions()->setSource('/app/user.phel'))
                ->getPhpCode();
        } finally {
            restore_error_handler();
        }

        return $warnings;
    }
}
