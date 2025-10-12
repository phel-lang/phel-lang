<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function extension_loaded;
use function sprintf;
use function version_compare;

final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('doctor')
            ->setDescription('Check system requirements for running the Phel CLI');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Checking requirements:');

        $normalizedVersion = PHP_VERSION;

        $requirements = [
            ['label' => 'PHP >= 8.3', 'status' => version_compare($normalizedVersion, '8.3.0', '>=')],
            ['label' => 'json extension', 'status' => extension_loaded('json')],
            ['label' => 'mbstring extension', 'status' => extension_loaded('mbstring')],
            ['label' => 'readline extension', 'status' => extension_loaded('readline')],
        ];

        $success = true;
        foreach ($requirements as $req) {
            $ok = $req['status'];
            $success = $success && $ok;
            $output->writeln(sprintf(' - %s: %s', $req['label'], $ok ? '<info>OK</info>' : '<error>FAIL</error>'));
        }

        if ($success) {
            $output->writeln('<info>Your system meets all requirements.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Your system does not meet all requirements.</error>');

        return Command::FAILURE;
    }
}
