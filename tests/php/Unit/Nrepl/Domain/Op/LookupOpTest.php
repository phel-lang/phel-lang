<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Api\Transfer\PhelFunction;
use Phel\Nrepl\Application\Op\LookupOp;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\Facade\ApiFacadeInterface;
use PHPUnit\Framework\TestCase;

final class LookupOpTest extends TestCase
{
    public function test_it_returns_info_when_symbol_is_resolved(): void
    {
        $fn = new PhelFunction(
            namespace: 'string',
            name: 'upper-case',
            doc: 'Uppercases s',
            signatures: ['(upper-case s)'],
            description: '',
            file: 'src/phel/string.phel',
            line: 10,
        );

        $api = $this->createStub(ApiFacadeInterface::class);
        $api->method('findSymbolMetadata')->willReturn($fn);

        $op = new LookupOp($api);
        $responses = $op->handle(new OpRequest('lookup', 'r1', null, [
            'op' => 'lookup',
            'sym' => 'string/upper-case',
        ]));

        self::assertCount(1, $responses);
        $info = $responses[0]->payload['info'];
        self::assertSame('upper-case', $info['name']);
        self::assertSame('string', $info['ns']);
        self::assertSame('(upper-case s)', $info['arglists-str']);
        self::assertSame('src/phel/string.phel', $info['file']);
        self::assertSame(10, $info['line']);
    }

    public function test_it_reports_no_info_when_finder_returns_null(): void
    {
        $api = $this->createStub(ApiFacadeInterface::class);
        $api->method('findSymbolMetadata')->willReturn(null);

        $op = new LookupOp($api);
        $responses = $op->handle(new OpRequest('lookup', 'r1', null, [
            'op' => 'lookup',
            'sym' => 'ghost',
        ]));

        self::assertContains('no-info', $responses[0]->payload['status']);
    }

    public function test_it_rejects_missing_sym_param(): void
    {
        $op = new LookupOp($this->createStub(ApiFacadeInterface::class));
        $responses = $op->handle(new OpRequest('lookup', 'r1', null, ['op' => 'lookup']));

        self::assertContains('error', $responses[0]->payload['status']);
    }

    public function test_it_uses_configured_op_name(): void
    {
        $op = new LookupOp($this->createStub(ApiFacadeInterface::class), 'info');
        self::assertSame('info', $op->name());

        $op2 = new LookupOp($this->createStub(ApiFacadeInterface::class), 'eldoc');
        self::assertSame('eldoc', $op2->name());
    }

    public function test_it_uses_session_namespace_when_resolving_bare_symbols(): void
    {
        $sessions = new SessionRegistry();
        $session = $sessions->create();
        $session->setNamespace('my.app');

        $api = $this->createMock(ApiFacadeInterface::class);
        $api->expects(self::once())
            ->method('findSymbolMetadata')
            ->with('helper', 'my.app')
            ->willReturn(null);

        $op = new LookupOp($api, 'lookup', $sessions);
        $op->handle(new OpRequest('lookup', 'r1', $session->id, [
            'op' => 'lookup',
            'sym' => 'helper',
            'session' => $session->id,
        ]));
    }

    public function test_explicit_ns_param_overrides_session(): void
    {
        $sessions = new SessionRegistry();
        $session = $sessions->create();
        $session->setNamespace('my.app');

        $api = $this->createMock(ApiFacadeInterface::class);
        $api->expects(self::once())
            ->method('findSymbolMetadata')
            ->with('helper', 'other.ns')
            ->willReturn(null);

        $op = new LookupOp($api, 'lookup', $sessions);
        $op->handle(new OpRequest('lookup', 'r1', $session->id, [
            'op' => 'lookup',
            'sym' => 'helper',
            'ns' => 'other.ns',
            'session' => $session->id,
        ]));
    }

    public function test_it_defaults_to_user_namespace_without_session(): void
    {
        $api = $this->createMock(ApiFacadeInterface::class);
        $api->expects(self::once())
            ->method('findSymbolMetadata')
            ->with('helper', 'user')
            ->willReturn(null);

        $op = new LookupOp($api);
        $op->handle(new OpRequest('lookup', 'r1', null, [
            'op' => 'lookup',
            'sym' => 'helper',
        ]));
    }
}
