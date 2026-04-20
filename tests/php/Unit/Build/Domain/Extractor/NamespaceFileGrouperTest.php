<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Extractor;

use Phel\Build\Domain\Extractor\NamespaceFileGrouper;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use PHPUnit\Framework\TestCase;

final class NamespaceFileGrouperTest extends TestCase
{
    private NamespaceFileGrouper $grouper;

    protected function setUp(): void
    {
        $sorter = new class() implements NamespaceSorterInterface {
            public function sort(array $namespaces, array $dependencies): array
            {
                return $namespaces;
            }
        };

        $this->grouper = new NamespaceFileGrouper($sorter);
    }

    public function test_local_primary_wins_over_phar_bundle(): void
    {
        $pharInfo = new NamespaceInformation('phar:///tmp/phel.phar/src/phel/core.phel', 'phel\\core', []);
        $localInfo = new NamespaceInformation('/workspace/src/phel/core.phel', 'phel\\core', []);

        $result = $this->grouper->groupAndSort([$pharInfo, $localInfo]);

        self::assertCount(1, $result);
        self::assertSame('/workspace/src/phel/core.phel', $result[0]->getFile());
    }

    public function test_local_primary_wins_even_when_phar_iterated_last(): void
    {
        $localInfo = new NamespaceInformation('/workspace/src/phel/core.phel', 'phel\\core', []);
        $pharInfo = new NamespaceInformation('phar:///tmp/phel.phar/src/phel/core.phel', 'phel\\core', []);

        $result = $this->grouper->groupAndSort([$localInfo, $pharInfo]);

        self::assertCount(1, $result);
        self::assertSame('/workspace/src/phel/core.phel', $result[0]->getFile());
    }

    public function test_two_local_definitions_keep_last_wins_behavior(): void
    {
        $a = new NamespaceInformation('/workspace/src/user_a.phel', 'user', []);
        $b = new NamespaceInformation('/workspace/src/user_b.phel', 'user', []);

        $result = $this->grouper->groupAndSort([$a, $b]);

        self::assertCount(1, $result);
        self::assertSame('/workspace/src/user_b.phel', $result[0]->getFile());
    }

    public function test_two_phar_definitions_keep_last_wins_behavior(): void
    {
        $a = new NamespaceInformation('phar:///tmp/one.phar/src/x.phel', 'ns\\x', []);
        $b = new NamespaceInformation('phar:///tmp/two.phar/src/x.phel', 'ns\\x', []);

        $result = $this->grouper->groupAndSort([$a, $b]);

        self::assertCount(1, $result);
        self::assertSame('phar:///tmp/two.phar/src/x.phel', $result[0]->getFile());
    }

    public function test_local_secondaries_are_preserved_alongside_phar_primary(): void
    {
        $pharPrimary = new NamespaceInformation('phar:///tmp/phel.phar/src/phel/core.phel', 'phel\\core', []);
        $localPrimary = new NamespaceInformation('/workspace/src/phel/core.phel', 'phel\\core', []);
        $localSecondary = new NamespaceInformation(
            '/workspace/src/phel/core_helpers.phel',
            'phel\\core',
            [],
            isPrimaryDefinition: false,
        );

        $result = $this->grouper->groupAndSort([$pharPrimary, $localPrimary, $localSecondary]);

        self::assertCount(2, $result);
        self::assertSame('/workspace/src/phel/core.phel', $result[0]->getFile());
        self::assertSame('/workspace/src/phel/core_helpers.phel', $result[1]->getFile());
    }
}
