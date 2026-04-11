<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Lang\SourceLocation;

/**
 * Represents a Clojure-style tagged literal: `#<tag> <form>`.
 *
 * The tag is an identifier (e.g. `cpp`, `uuid`, `inst`) and the form is
 * the next parseable expression. Phel does not register any tags natively
 * — the node is emitted so that unknown tags inside non-selected reader
 * conditional branches parse cleanly. Known-tag dispatch can be layered
 * on top of this node later.
 */
final readonly class TaggedLiteralNode implements NodeInterface
{
    public function __construct(
        private string $tag,
        private NodeInterface $form,
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
    ) {}

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getForm(): NodeInterface
    {
        return $this->form;
    }

    public function getCode(): string
    {
        return '#' . $this->tag . $this->form->getCode();
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }
}
