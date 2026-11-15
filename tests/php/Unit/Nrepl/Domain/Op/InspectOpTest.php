<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\InspectOp;
use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Bencode\BencodeEncoder;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\Session;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\Eval\EvalResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use WeakReference;

use function strlen;

final class InspectOpTest extends TestCase
{
    public function test_it_evaluates_once_and_bounds_pages_and_output(): void
    {
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::once())->method('structuredEval')->willReturn(EvalResult::success(array_fill(0, 1000, str_repeat('x', 100000)), str_repeat('out', 1000)));
        $registry = new SessionRegistry();
        $session = $registry->create();
        $binder = new SessionNamespaceBinder($this->createStub(CompilerFacadeInterface::class), $registry);
        $start = new InspectOp(InspectOp::START, $run, $binder);
        $result = $start->handle(new OpRequest(InspectOp::START, 'start', $session->id, ['code' => 'once']))[0]->payload;
        self::assertTrue($result['output-truncated']);
        self::assertNull($session->lastValue());
        $page = new InspectOp(InspectOp::PAGE, $run, $binder);
        $request = new OpRequest(InspectOp::PAGE, 'page', $session->id, ['inspection' => $result['inspection'], 'handle' => $result['root']['handle'], 'limit' => 32]);
        $response = $page->handle($request)[0]->payload;
        self::assertCount(32, $response['children']);
        self::assertSame($response, $page->handle($request)[0]->payload);
        self::assertLessThanOrEqual(InspectOp::MAX_RESPONSE_BYTES, strlen(new BencodeEncoder()->encode($response)));
        $release = new InspectOp(InspectOp::RELEASE, $run, $binder);
        self::assertTrue($release->handle(new OpRequest(InspectOp::RELEASE, 'release', $session->id, ['inspection' => $result['inspection']]))[0]->payload['released']);
        self::assertContains('inspection-error', $page->handle($request)[0]->payload['status']);
    }

    public function test_session_close_releases_values_even_when_the_session_and_tree_are_still_referenced(): void
    {
        $registry = new SessionRegistry();
        $session = $registry->create();
        $value = new stdClass();
        $weak = WeakReference::create($value);
        $inspection = $session->inspect($value);
        unset($value);
        $registry->close($session->id);
        self::assertNull($weak->get());
        $this->expectException(RuntimeException::class);
        $inspection->root();
    }

    public function test_throwing_destructors_cannot_keep_a_session_or_its_handles_alive(): void
    {
        $registry = new SessionRegistry();
        $session = $registry->create();
        $value = new class() {
            public object $child;

            public function __destruct()
            {
                throw new RuntimeException('destructor failed');
            }
        };
        $value->child = new stdClass();

        $weak = WeakReference::create($value->child);
        $inspection = $session->inspect($value);
        $inspection->page($inspection->id(), 0, 1);
        unset($value);
        self::assertTrue($registry->close($session->id));
        self::assertNull($registry->get($session->id));
        self::assertNull($weak->get());
        $this->expectException(RuntimeException::class);
        $inspection->root();
    }

    public function test_it_rejects_cross_session_handles_and_capacity_before_evaluation(): void
    {
        $registry = new SessionRegistry();
        $owner = $registry->create();
        $other = $registry->create();
        $inspection = $owner->inspect(42);
        $run = $this->createMock(RunFacadeInterface::class);
        $run->expects(self::never())->method('structuredEval');
        $binder = new SessionNamespaceBinder($this->createStub(CompilerFacadeInterface::class), $registry);
        $page = new InspectOp(InspectOp::PAGE, $run, $binder);
        self::assertContains('inspection-error', $page->handle(new OpRequest(InspectOp::PAGE, 'page', $other->id, ['inspection' => $inspection->id(), 'handle' => $inspection->id()]))[0]->payload['status']);
        for ($i = 1; $i < Session::MAX_INSPECTIONS; ++$i) {
            $owner->inspect($i);
        }

        $start = new InspectOp(InspectOp::START, $run, $binder);
        self::assertContains('inspection-error', $start->handle(new OpRequest(InspectOp::START, 'start', $owner->id, ['code' => 'never']))[0]->payload['status']);
        $huge = $page->handle(new OpRequest(InspectOp::PAGE, str_repeat('x', 100000), $owner->id, []))[0]->payload;
        self::assertLessThanOrEqual(InspectOp::MAX_RESPONSE_BYTES, strlen(new BencodeEncoder()->encode($huge)));
    }
}
