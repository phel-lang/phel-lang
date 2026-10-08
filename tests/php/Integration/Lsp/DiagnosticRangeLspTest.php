<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lsp\Application\Convert\DiagnosticConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Diagnostics carry 1-based columns and LSP positions are 0-based, so the
 * shift must happen exactly once, at the protocol edge.
 */
final class DiagnosticRangeLspTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_diagnostic_range_covers_the_characters_the_error_names(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        // Line 1 is "(def m {:a 1 :a 2})": the repeated `:a` is characters 13 and 14.
        $diagnostics = new ApiFacade()->analyzeSource("(ns user)\n(def m {:a 1 :a 2})", 'file:///project/user.phel');
        $converter = new DiagnosticConverter(new PositionConverter(), new UriConverter());

        self::assertSame('PHEL203', $diagnostics[0]->code ?? null);
        self::assertSame([
            'start' => ['line' => 1, 'character' => 13],
            'end' => ['line' => 1, 'character' => 15],
        ], $converter->toLspDiagnostic($diagnostics[0])['range']);
    }
}
