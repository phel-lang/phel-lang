<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain;

use Phel\Nrepl\Domain\BindAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BindAddressTest extends TestCase
{
    #[DataProvider('loopbackHosts')]
    public function test_a_loopback_host_is_local_only(string $host): void
    {
        self::assertTrue(BindAddress::isLoopback($host));
    }

    #[DataProvider('reachableHosts')]
    public function test_any_other_host_is_reachable_from_outside(string $host): void
    {
        self::assertFalse(BindAddress::isLoopback($host));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function loopbackHosts(): iterable
    {
        yield 'ipv4' => ['127.0.0.1'];
        yield 'ipv4 range' => ['127.1.2.3'];
        yield 'ipv6' => ['::1'];
        yield 'bracketed ipv6' => ['[::1]'];
        yield 'localhost' => ['localhost'];
        yield 'localhost upper case' => ['LocalHost'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reachableHosts(): iterable
    {
        yield 'any ipv4' => ['0.0.0.0'];
        yield 'any ipv6' => ['::'];
        yield 'lan address' => ['192.168.1.10'];
        yield 'host name' => ['dev.example.com'];
        yield 'look-alike' => ['127.example.com'];
    }
}
