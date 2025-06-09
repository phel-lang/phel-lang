<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Doctor;

use Phel\Run\Infrastructure\Command\DoctorCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DoctorCommandTest extends TestCase
{
    public function test_doctor_command_outputs_success(): void
    {
        $command = new DoctorCommand();

        $this->expectOutputRegex('/Your system meets all requirements/');

        $command->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );
    }

    private function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(static fn (string $str): int => print $str . PHP_EOL);
        $output->method('write')
            ->willReturnCallback(static fn (string $str): int => print $str);

        return $output;
    }
}
