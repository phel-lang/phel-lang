<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Lint\Application\LoadedFormsReader;
use Phel\Lint\Application\SourceReader;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function getmypid;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class LoadedFormsReaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $this->dir = sys_get_temp_dir() . '/phel-loaded-forms-' . getmypid() . '-' . uniqid();
        mkdir($this->dir . '/app', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reads_nested_loads_into_the_same_namespace(): void
    {
        $this->write('app.phel', "(ns app)\n(load \"app/one\")");
        $this->write('app/one.phel', "(in-ns app)\n(def one 1)\n(when true (load \"two\"))");
        $this->write('app/two.phel', "(def two 2)\n(load \"one\")");

        self::assertSame(
            ['(in-ns app)', '(def one 1)', '(when true (load "two"))', '(def two 2)', '(load "one")'],
            $this->printed($this->readLoaded('app.phel')),
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_null_when_a_loaded_file_switches_namespace(): void
    {
        $this->write('app.phel', "(ns app)\n(load \"app/one\")");
        $this->write('app/one.phel', "(in-ns app)\n(in-ns other)\n(def two 2)\n(in-ns app)\n(def one 1)");

        self::assertNull($this->readLoaded('app.phel'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_loads_after_the_file_switches_namespace(): void
    {
        $this->write('app.phel', "(ns app)\n(load \"app/one\")\n(in-ns other)\n(load \"app/nowhere\")");
        $this->write('app/one.phel', '(def one 1)');

        self::assertSame(['(def one 1)'], $this->printed($this->readLoaded('app.phel')));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_follows_a_load_inside_a_set_literal(): void
    {
        $this->write('app.phel', "(ns app)\n#{(load \"app/one\")}");
        $this->write('app/one.phel', '(def one 1)');

        self::assertSame(['(def one 1)'], $this->printed($this->readLoaded('app.phel')));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_null_when_a_loaded_file_switches_namespace_by_string(): void
    {
        $this->write('app.phel', "(ns app)\n(load \"app/one\")");
        $this->write('app/one.phel', "(in-ns \"other\")\n(def two 2)\n(in-ns \"app\")");

        self::assertNull($this->readLoaded('app.phel'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_ignores_quoted_and_qualified_load_forms(): void
    {
        $this->write('app.phel', "(ns app)\n'(load \"app/nowhere\")\n(other/load \"app/nowhere\")");

        self::assertSame([], $this->readLoaded('app.phel'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_prefers_the_bare_path_next_to_the_caller_as_the_runtime_does(): void
    {
        mkdir($this->dir . '/app/app');
        $this->write('app/main.phel', "(ns app.main)\n(load \"helper\")");
        $this->write('app/helper.phel', '(def bare 1)');
        $this->write('app/app/helper.phel', '(def prefixed 1)');

        self::assertSame(['(def bare 1)'], $this->printed($this->readLoaded('app/main.phel')));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_null_for_a_computed_load_path(): void
    {
        $this->write('app.phel', "(ns app)\n(load (str \"app/\" \"one\"))");

        self::assertNull($this->readLoaded('app.phel'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_null_for_a_missing_load_target(): void
    {
        $this->write('app.phel', "(ns app)\n(load \"app/nowhere\")");

        self::assertNull($this->readLoaded('app.phel'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_nothing_for_a_file_without_namespace(): void
    {
        $this->write('script.phel', '(load "app/nowhere")');

        self::assertSame([], $this->readLoaded('script.phel'));
    }

    /**
     * @return list<bool|float|int|string|TypeInterface|null>|null
     */
    private function readLoaded(string $file): ?array
    {
        $compiler = new CompilerFacade();
        $path = $this->dir . '/' . $file;
        $read = new SourceReader($compiler, new LoadedFormsReader($compiler))->read((string) file_get_contents($path), $path);

        return new LoadedFormsReader($compiler)->read($read->forms, $path, $read->namespace);
    }

    /**
     * @param list<bool|float|int|string|TypeInterface|null>|null $forms
     *
     * @return list<string>
     */
    private function printed(?array $forms): array
    {
        self::assertNotNull($forms);

        return array_map(static fn(mixed $form): string => Printer::readable()->print($form), $forms);
    }

    private function write(string $file, string $source): void
    {
        file_put_contents($this->dir . '/' . $file, $source);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : unlink($entry);
        }

        @rmdir($dir);
    }
}
