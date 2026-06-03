<?php

declare(strict_types=1);

namespace PhelTest\Unit\Config;

use Phel\Config\PhelConfig;
use Phel\Config\PhelConfigValidator;
use PHPUnit\Framework\TestCase;

final class PhelConfigValidatorTest extends TestCase
{
    private PhelConfigValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PhelConfigValidator();
    }

    public function test_default_config_is_valid(): void
    {
        $errors = $this->validator->validate(new PhelConfig());

        self::assertSame([], $errors);
    }

    public function test_empty_dir_arrays_produce_no_errors(): void
    {
        $config = new PhelConfig()
            ->withSrcDirs([])
            ->withTestDirs([]);

        $errors = $this->validator->validate($config);

        self::assertSame([], $errors);
    }

    public function test_empty_vendor_dir_is_allowed(): void
    {
        $config = new PhelConfig()->withVendorDir('');

        $errors = $this->validator->validate($config);

        self::assertSame([], $errors);
    }

    public function test_relative_vendor_dir_with_dots_is_allowed(): void
    {
        $config = new PhelConfig()->withVendorDir('../vendor');

        $errors = $this->validator->validate($config);

        self::assertSame([], $errors);
    }

    public function test_absolute_src_dir_reports_error_with_label_and_path(): void
    {
        $config = new PhelConfig()->withSrcDirs(['/absolute/src']);

        $errors = $this->validator->validate($config);

        self::assertSame(
            ["Source directory '/absolute/src' should be relative, not absolute"],
            $errors,
        );
    }

    public function test_each_absolute_dir_reports_its_own_error(): void
    {
        $config = new PhelConfig()
            ->withSrcDirs(['src', '/abs/one', '/abs/two']);

        $errors = $this->validator->validate($config);

        self::assertCount(2, $errors);
        self::assertContains("Source directory '/abs/one' should be relative, not absolute", $errors);
        self::assertContains("Source directory '/abs/two' should be relative, not absolute", $errors);
    }

    public function test_errors_from_src_test_and_vendor_are_aggregated(): void
    {
        $config = new PhelConfig()
            ->withSrcDirs(['/abs/src'])
            ->withTestDirs(['/abs/test'])
            ->withVendorDir('/abs/vendor');

        $errors = $this->validator->validate($config);

        self::assertSame(
            [
                "Source directory '/abs/src' should be relative, not absolute",
                "Test directory '/abs/test' should be relative, not absolute",
                "Vendor directory '/abs/vendor' should be relative, not absolute",
            ],
            $errors,
        );
    }
}
