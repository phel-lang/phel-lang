<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Application;

use Phel\Compiler\Application\GlobalEnvironmentManager;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentRegistry;
use Phel\Shared\TagResolver;
use PHPUnit\Framework\TestCase;

final class GlobalEnvironmentManagerTest extends TestCase
{
    private ?GlobalEnvironmentInterface $previous;

    protected function setUp(): void
    {
        $this->previous = GlobalEnvironmentRegistry::get();
    }

    protected function tearDown(): void
    {
        GlobalEnvironmentRegistry::set($this->previous);
        TagResolver::setUseAliasResolver(null);
    }

    public function test_reset_clears_the_tag_use_alias_resolver(): void
    {
        TagResolver::setUseAliasResolver(
            static fn(string $alias): ?string => $alias === 'Moment' ? 'DateTimeImmutable' : null,
        );

        new GlobalEnvironmentManager()->reset();

        self::assertSame('Moment', TagResolver::normalizeScalar('Moment'));
    }
}
