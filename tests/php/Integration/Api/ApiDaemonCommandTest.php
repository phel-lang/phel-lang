<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Gacela\Framework\Gacela;
use Phel;
use Phel\Api\ApiFacade;
use Phel\Api\Infrastructure\Command\ApiDaemonCommand;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

use function dirname;

final class ApiDaemonCommandTest extends TestCase
{
    use RemoveDirTrait;

    public static function providerNamespaces(): iterable
    {
        yield 'project namespace' => ['parity.provider'];
        yield 'phel namespace' => ['phel.parity-provider'];
    }

    #[DataProvider('providerNamespaces')]
    public function test_saved_dependency_edits_agree_across_persistent_and_fresh_tools(string $providerNamespace): void
    {
        $project = sys_get_temp_dir() . '/phel-diagnostic-parity-' . uniqid('', true);
        mkdir($project . '/src', 0o777, true);
        $project = (string) realpath($project);
        $provider = $project . '/src/provider.phel';
        $consumer = $project . '/src/consumer.phel';
        file_put_contents($project . '/phel-config.php', <<<'PHP'
<?php
return \Phel\Config\PhelConfig::forProject()->withSrcDirs(['src']);
PHP);
        $validProvider = "(ns {$providerNamespace})\n(defn value [] 3)\n";
        $invalidProvider = "(ns {$providerNamespace} (:require phel.string :as clash) (:require phel.json :as clash))\n(defn value [] 3)\n";
        $oneArgumentProvider = "(ns {$providerNamespace})\n(defn value [x] x)\n";
        $validConsumer = "(ns parity.consumer (:require {$providerNamespace} :refer [value]))\n(defn result [] (do \"é🐘\" (value)))\n";
        file_put_contents($consumer, $validConsumer);
        $input = new InputStream();
        $daemon = $this->phelProcess($project, ['api-daemon']);
        $daemon->setTimeout(120);
        $daemon->setInput($input);

        try {
            $daemon->start();
            $steps = [
                'invalid provider' => [$invalidProvider, $validConsumer, 'PHEL001'],
                'fixed provider' => [$validProvider, $validConsumer, null],
                'changed signature' => [$oneArgumentProvider, $validConsumer, 'PHEL002'],
                'restored signature' => [$validProvider, $validConsumer, null],
                'removed definition' => ["(ns {$providerNamespace})\n(defn other [] 3)\n", $validConsumer, ['PHEL013', 'PHEL001']],
                'restored definition' => [$validProvider, $validConsumer, null],
                'invalid provider again' => [$invalidProvider, $validConsumer, 'PHEL001'],
                'repaired provider again' => [$validProvider, $validConsumer, null],
                'deleted provider' => [null, $validConsumer, 'PHEL001'],
                'recreated provider' => [$validProvider, $validConsumer, null],
                'consumer edit with cached provider' => [$validProvider, str_replace('(value)', '(value 1)', $validConsumer), 'PHEL002'],
                'fixed consumer' => [$validProvider, $validConsumer, null],
            ];
            foreach ($steps as $id => [$providerSource, $consumerSource, $expectedCode]) {
                if ($providerSource === null) {
                    unlink($provider);
                } elseif (!is_file($provider) || file_get_contents($provider) !== $providerSource) {
                    file_put_contents($provider, $providerSource);
                }

                file_put_contents($consumer, $consumerSource);
                $analyzed = $this->commandDiagnostics($project, ['analyze', $consumer]);
                self::assertSame($expectedCode === null ? [] : (array) $expectedCode, array_column($analyzed, 'errorCode'), $id);
                $response = $this->requestAnalysis($daemon, $input, $id, $consumer, $consumerSource);
                self::assertSame($analyzed, $response, $id . ': persistent daemon ' . $daemon->getErrorOutput());
                $cached = $this->commandDiagnostics($project, ['lint', $consumer, '--format=json']);
                self::assertSame($analyzed, $cached, $id . ': cached lint');
                self::assertSame($cached, $this->commandDiagnostics($project, ['lint', $consumer, '--format=json']), $id . ': warm lint');
                self::assertSame($cached, $this->commandDiagnostics($project, ['lint', $consumer, '--format=json', '--no-cache']), $id . ': uncached lint');
            }

            $unsaved = str_replace('(value)', '(value 1)', $validConsumer);
            $unsavedDiagnostics = $this->requestAnalysis($daemon, $input, 'unsaved', $consumer, $unsaved);
            self::assertSame(['PHEL002'], array_column($unsavedDiagnostics, 'errorCode'));
            self::assertSame($validConsumer, file_get_contents($consumer));
            self::assertSame([], $this->requestAnalysis($daemon, $input, 'saved after unsaved', $consumer, $validConsumer));

            $stdlibConsumer = "(ns parity.http)\n(phel.http-client/get)\n";
            file_put_contents($consumer, $stdlibConsumer);
            $stdlibDiagnostics = $this->commandDiagnostics($project, ['analyze', $consumer]);
            self::assertSame(['PHEL002'], array_column($stdlibDiagnostics, 'errorCode'));
            self::assertSame($stdlibDiagnostics, $this->requestAnalysis($daemon, $input, 'stdlib first request', $consumer, $stdlibConsumer));
            self::assertSame($stdlibDiagnostics, $this->requestAnalysis($daemon, $input, 'stdlib second request', $consumer, $stdlibConsumer));

            $macroBuffer = "(ns parity.buffer)\n(defmacro identity [x] x)\n";
            self::assertSame([], $this->requestAnalysis($daemon, $input, 'unsaved provider', $project . '/src/buffer.phel', $macroBuffer));
            $savedWithBuffer = str_replace('(value)', '(parity.buffer/identity (value))', $validConsumer);
            file_put_contents($consumer, $savedWithBuffer);
            self::assertSame([], $this->requestAnalysis($daemon, $input, 'saved consumer with unsaved provider', $consumer, $savedWithBuffer));

            $loader = "(ns parity.loader)\n(defn helper [] 1)\n(load \"fragment\")\n";
            $fragment = "(in-ns parity.loader)\n(defn result [] (helper))\n";
            $fragmentPath = $project . '/src/fragment.phel';
            file_put_contents($consumer, $loader);
            file_put_contents($fragmentPath, $fragment);
            $unsavedFragment = "(in-ns parity.loader)\n(defn result [x] x)\n(result)\n";
            self::assertSame(['PHEL002'], array_column($this->requestAnalysis($daemon, $input, 'unsaved fragment', $fragmentPath, $unsavedFragment), 'errorCode'));
            self::assertSame([], $this->requestAnalysis($daemon, $input, 'saved loader', $consumer, $loader));
            self::assertSame([], $this->requestAnalysis($daemon, $input, 'saved fragment', $fragmentPath, $fragment));
            self::assertSame([], $this->commandDiagnostics($project, ['analyze', $fragmentPath]));

            $input->close();
            self::assertSame(0, $daemon->wait(), $daemon->getErrorOutput());
        } finally {
            $input->close();
            $daemon->stop(1);
            $this->removeDir($project);
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_crash_is_reported_on_stderr_only(): void
    {
        Phel::bootstrap(__DIR__);
        Gacela::overrideExistingResolvedClass(ApiFacade::class, new class() {
            public function createApiDaemon(): never
            {
                throw new RuntimeException('daemon broke');
            }
        });

        $tester = new CommandTester(new ApiDaemonCommand());
        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('daemon broke', $tester->getErrorOutput());
    }

    private function phelProcess(string $project, array $args): Process
    {
        return new Process([PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', ...$args], $project, ['PHEL_NO_OPCACHE_REEXEC' => '1'], timeout: 30);
    }

    private function commandDiagnostics(string $project, array $args): array
    {
        $process = $this->phelProcess($project, $args);
        $process->run();
        self::assertContains($process->getExitCode(), [0, 1], $process->getErrorOutput());
        $diagnostics = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($diagnostics);
        self::assertSame(array_any($diagnostics, static fn(array $d): bool => $d['severity'] === 'error') ? 1 : 0, $process->getExitCode());

        return $this->semanticDiagnostics($diagnostics);
    }

    private function requestAnalysis(Process $daemon, InputStream $input, string $id, string $uri, string $source): array
    {
        $daemon->clearOutput();
        $input->write(json_encode(['id' => $id, 'method' => 'analyzeSource', 'params' => ['source' => $source, 'uri' => $uri]], JSON_THROW_ON_ERROR) . "\n");
        $received = $daemon->waitUntil(static fn(string $type, string $output): bool => $type === Process::OUT && str_contains($daemon->getOutput(), "\n"));
        self::assertTrue($received, $daemon->getErrorOutput());
        $response = json_decode(trim($daemon->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($id, $response['id']);
        self::assertArrayNotHasKey('error', $response);

        return $this->semanticDiagnostics($response['result']);
    }

    private function semanticDiagnostics(array $diagnostics): array
    {
        $result = [];
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic['errorCode'] === null) {
                continue;
            }

            $result[] = array_intersect_key($diagnostic, array_flip(['errorCode', 'severity', 'uri', 'startLine', 'startCol', 'endLine', 'endCol']));
        }

        usort($result, static fn(array $left, array $right): int => [$left['startLine'], $left['startCol'], $left['errorCode']] <=> [$right['startLine'], $right['startCol'], $right['errorCode']]);

        return $result;
    }
}
