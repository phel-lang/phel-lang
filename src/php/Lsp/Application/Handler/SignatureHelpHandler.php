<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Handler;

use Phel\Api\ApiFacade;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;

/**
 * Handles `textDocument/signatureHelp` for PHP-interop calls: shows the
 * signature of the constructor / method being called inside `(php/new ...)`,
 * `(php/-> recv (method ...))`, or `(php/:: Class (method ...))`. Returns null
 * for any other position.
 */
final readonly class SignatureHelpHandler implements HandlerInterface
{
    public function __construct(
        private ApiFacade $apiFacade,
        private ParamsExtractor $params,
    ) {}

    public function method(): string
    {
        return 'textDocument/signatureHelp';
    }

    public function isNotification(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handle(array $params, Session $session): mixed
    {
        $uri = $this->params->uri($params);
        $position = $this->params->position($params);
        if ($uri === '' || $position === null) {
            return null;
        }

        $document = $session->documents()->get($uri);
        if (!$document instanceof Document) {
            return null;
        }

        [$line, $col] = $document->oneBasedLineCol($position);

        return $this->apiFacade->phpInteropSignatureAt($document->text, $line, $col);
    }
}
