<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel;
use Phel\Build\Application\MissingRequireReporter;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\Exceptions\CompilerException;
use PHPUnit\Framework\TestCase;

final class MissingRequireReporterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
    }

    public function test_a_requirer_with_no_source_file_gets_the_bare_message(): void
    {
        $error = new MissingRequireReporter(new CompilerFacade())->error('Cannot find namespace', 'app.helpers', 'repl', []);

        self::assertInstanceOf(ExtractorException::class, $error);
        self::assertSame('Cannot find namespace', $error->getMessage());
    }

    public function test_a_namespace_the_ns_form_does_not_name_gets_the_bare_message(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'phel-missing-ns');
        file_put_contents($file, "(ns app.util)\n");

        try {
            $error = new MissingRequireReporter(new CompilerFacade())->error('Cannot find namespace', 'app.helpers', $file, []);
        } finally {
            unlink($file);
        }

        self::assertInstanceOf(ExtractorException::class, $error);
    }

    public function test_it_locates_a_flat_require_and_leaves_out_an_empty_search_note(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'phel-missing-ns');
        file_put_contents($file, "(ns app.util\n  (:require app.helpers :as h))\n");

        try {
            $error = new MissingRequireReporter(new CompilerFacade())->error('Cannot find namespace', 'app.helpers', $file, []);
        } finally {
            unlink($file);
        }

        self::assertInstanceOf(CompilerException::class, $error);
        self::assertSame(2, $error->getNestedException()->getStartLocation()?->getLine());
        self::assertNull($error->getNestedException()->getRelatedLocationNote());
    }
}
