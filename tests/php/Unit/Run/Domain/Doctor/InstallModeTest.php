<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Doctor;

use Phel\Run\Domain\Doctor\InstallMode;
use PHPUnit\Framework\TestCase;

final class InstallModeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-install-mode-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/deps/phel-lang/phel-lang', 0755, true);
        mkdir($this->root . '/deps/composer');
        touch($this->root . '/deps/composer/installed.json');
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/deps/composer/installed.json');
        rmdir($this->root . '/deps/composer');
        rmdir($this->root . '/deps/phel-lang/phel-lang');
        rmdir($this->root . '/deps/phel-lang');
        rmdir($this->root . '/deps');
        rmdir($this->root);
    }

    public function test_a_phar_run_wins(): void
    {
        self::assertSame(InstallMode::Phar, InstallMode::detect('/opt/phel.phar', $this->root . '/deps/phel-lang/phel-lang'));
    }

    public function test_composer_with_a_custom_vendor_dir(): void
    {
        self::assertSame(InstallMode::Composer, InstallMode::detect('', $this->root . '/deps/phel-lang/phel-lang'));
    }

    public function test_a_checkout_named_like_the_package_without_composer_metadata(): void
    {
        self::assertSame(InstallMode::SourceCheckout, InstallMode::detect('', $this->root . '/elsewhere/phel-lang/phel-lang'));
        self::assertSame(InstallMode::SourceCheckout, InstallMode::detect('', $this->root));
    }
}
