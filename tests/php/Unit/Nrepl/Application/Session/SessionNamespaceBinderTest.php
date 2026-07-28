<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Application\Session;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\Session;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;

final class SessionNamespaceBinderTest extends TestCase
{
    public function test_bind_points_the_environment_at_the_session_namespace(): void
    {
        $sessions = new SessionRegistry();
        $session = $sessions->create();
        $session->setNamespace('alice.scratch');

        $env = $this->createMock(GlobalEnvironmentInterface::class);
        $env->expects(self::once())->method('setNs')->with('alice.scratch');

        new SessionNamespaceBinder($this->compilerWith($env), $sessions)
            ->bind($this->request($session->id));
    }

    /**
     * A session-less request has no namespace of its own, so the only sensible
     * ambient namespace is the one already in place. Moving it would be
     * inventing state the client never asked for.
     */
    public function test_bind_leaves_a_session_less_request_alone(): void
    {
        $env = $this->createMock(GlobalEnvironmentInterface::class);
        $env->expects(self::never())->method('setNs');

        new SessionNamespaceBinder($this->compilerWith($env), new SessionRegistry())
            ->bind($this->request(null));
    }

    public function test_bind_ignores_a_session_id_that_no_longer_exists(): void
    {
        $env = $this->createMock(GlobalEnvironmentInterface::class);
        $env->expects(self::never())->method('setNs');

        new SessionNamespaceBinder($this->compilerWith($env), new SessionRegistry())
            ->bind($this->request('closed-session'));
    }

    public function test_bind_is_a_no_op_before_the_global_environment_exists(): void
    {
        $sessions = new SessionRegistry();
        $session = $sessions->create();

        $compiler = $this->createMock(CompilerFacadeInterface::class);
        $compiler->method('isGlobalEnvironmentInitialized')->willReturn(false);
        $compiler->expects(self::never())->method('getGlobalEnvironment');

        new SessionNamespaceBinder($compiler, $sessions)->bind($this->request($session->id));
    }

    public function test_sync_records_the_post_eval_namespace_on_the_session(): void
    {
        $sessions = new SessionRegistry();
        $session = $sessions->create();

        $ns = new SessionNamespaceBinder($this->compilerInNamespace('foo.bar'), $sessions)->sync($session);

        self::assertSame('foo.bar', $ns);
        self::assertSame('foo.bar', $session->namespace());
    }

    /**
     * The analyzer stores a namespace in whatever separator form it was written
     * with; clients get the canonical dot form.
     */
    public function test_sync_reports_the_display_form_of_the_namespace(): void
    {
        $sessions = new SessionRegistry();
        $session = $sessions->create();

        $ns = new SessionNamespaceBinder($this->compilerInNamespace('foo\\bar-baz'), $sessions)->sync($session);

        self::assertSame('foo.bar-baz', $ns);
    }

    public function test_sync_falls_back_to_the_session_namespace_when_the_environment_reports_nothing(): void
    {
        $sessions = new SessionRegistry();
        $session = $sessions->create();
        $session->setNamespace('kept.ns');

        $ns = new SessionNamespaceBinder($this->compilerInNamespace(''), $sessions)->sync($session);

        self::assertSame('kept.ns', $ns);
    }

    public function test_sync_without_a_session_falls_back_to_the_default_namespace(): void
    {
        $compiler = $this->createStub(CompilerFacadeInterface::class);

        $ns = new SessionNamespaceBinder($compiler, new SessionRegistry())->sync(null);

        self::assertSame(Session::DEFAULT_NAMESPACE, $ns);
    }

    private function request(?string $session): OpRequest
    {
        return new OpRequest('eval', 'r1', $session, ['op' => 'eval']);
    }

    private function compilerWith(GlobalEnvironmentInterface $env): CompilerFacadeInterface
    {
        $compiler = $this->createStub(CompilerFacadeInterface::class);
        $compiler->method('isGlobalEnvironmentInitialized')->willReturn(true);
        $compiler->method('getGlobalEnvironment')->willReturn($env);

        return $compiler;
    }

    private function compilerInNamespace(string $ns): CompilerFacadeInterface
    {
        $env = $this->createStub(GlobalEnvironmentInterface::class);
        $env->method('getNs')->willReturn($ns);

        return $this->compilerWith($env);
    }
}
