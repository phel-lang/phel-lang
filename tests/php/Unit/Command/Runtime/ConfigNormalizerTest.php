<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Runtime;

use Phel\Command\Runtime\ConfigNormalizer;
use PHPUnit\Framework\TestCase;

final class ConfigNormalizerTest extends TestCase
{
    public function test_load_config(): void
    {
        $phelConfig = [
            'loader' => [
                'phel-package\\' => 'src/',
            ],
            'loader-dev' => [
                'phel-package-tests\\' => 'tests/',
            ],
        ];

        $configLoader = new ConfigNormalizer();
        $result = $configLoader->normalize([], $phelConfig, 'prefix');

        self::assertSame([
            'loader' => [
                'prefix/src/',
            ],
            'loader-dev' => [
                'prefix/tests/',
            ],
        ], $result);
    }
}
