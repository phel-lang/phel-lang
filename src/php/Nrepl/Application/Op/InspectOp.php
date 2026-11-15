<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Application\Session\SessionNamespaceBinder;
use Phel\Nrepl\Domain\Bencode\BencodeEncoder;
use Phel\Nrepl\Domain\Inspection\ValueInspection;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;
use Phel\Nrepl\Domain\Session\Session;
use Phel\Shared\CompileOptions;
use Phel\Shared\Facade\RunFacadeInterface;
use RuntimeException;
use Throwable;

use function is_int;
use function strlen;

/**
 * @internal
 */
final readonly class InspectOp implements OpHandlerInterface
{
    public const string START = 'phel-inspect-start';

    public const string PAGE = 'phel-inspect-page';

    public const string RELEASE = 'phel-inspect-release';

    public const int MAX_RESPONSE_BYTES = 16384;

    public function __construct(
        private string $operation,
        private RunFacadeInterface $runFacade,
        private SessionNamespaceBinder $namespaceBinder,
    ) {}

    public function name(): string
    {
        return $this->operation;
    }

    public function handle(OpRequest $request): array
    {
        try {
            if (strlen($request->id ?? '') > 256 || strlen($request->session ?? '') > 256) {
                return [OpResponse::build(null, null, ['message' => 'Inspection routing metadata exceeds limit.'], [OpStatus::ERROR, OpStatus::DONE])];
            }

            $session = $this->namespaceBinder->sessionFor($request)
                ?? throw new RuntimeException('Inspection requires a live session.');
            $body = match ($this->operation) {
                self::START => $this->start($request, $session),
                self::PAGE => $session->inspection($request->stringParam('inspection'))->page(
                    $request->stringParam('handle'),
                    $this->integerParam($request, 'offset', 0, ValueInspection::MAX_ITEMS),
                    $this->integerParam($request, 'limit', ValueInspection::PAGE_SIZE, ValueInspection::PAGE_SIZE),
                ),
                self::RELEASE => $this->release($request, $session),
                default => throw new RuntimeException('Unknown inspection operation.'),
            };
            $response = OpResponse::forRequest($request, $body, [OpStatus::DONE]);
            if (strlen(new BencodeEncoder()->encode($response->payload)) > self::MAX_RESPONSE_BYTES) {
                throw new RuntimeException('Inspection response exceeds byte limit.');
            }

            return [$response];
        } catch (Throwable $throwable) {
            return [OpResponse::errorDone($request, substr($throwable->getMessage(), 0, 512), ['inspection-error'])];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function start(OpRequest $request, Session $session): array
    {
        $session->requireInspectionCapacity();
        $code = $request->optionalStringParam('code') ?? throw new RuntimeException('Inspection requires code.');
        $this->namespaceBinder->bind($request);
        $result = $this->runFacade->structuredEval($code, new CompileOptions());
        $namespace = $this->namespaceBinder->sync($session);
        if ($this->namespaceBinder->sessionFor($request) !== $session) {
            throw new RuntimeException('Inspection session was closed while evaluating.');
        }

        if (!$result->success) {
            throw new RuntimeException($result->error->message ?? 'Inspection evaluation is incomplete.');
        }

        $inspection = $session->inspect($result->value);
        return [
            'inspection' => $inspection->id(),
            'root' => $inspection->root(),
            'ns' => substr($namespace, 0, 256),
            'out' => substr($result->output, 0, 512),
            'output-truncated' => strlen($result->output) > 512,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function release(OpRequest $request, Session $session): array
    {
        $session->releaseInspection($request->stringParam('inspection'));
        return ['released' => true];
    }

    private function integerParam(OpRequest $request, string $name, int $default, int $maximum): int
    {
        $value = $request->raw[$name] ?? $default;
        if (!is_int($value) || $value < 0 || $value > $maximum || ($name === 'limit' && $value === 0)) {
            throw new RuntimeException('Invalid inspection ' . $name . '.');
        }

        return $value;
    }
}
