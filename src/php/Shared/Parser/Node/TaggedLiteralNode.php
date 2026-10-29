<?php

declare(strict_types=1);

namespace Phel\Shared\Parser\Node;

use Phel\Lang\SourceLocation;

final readonly class TaggedLiteralNode implements NodeInterface
{
    public function __construct(
        private string $tag,
        private NodeInterface $form,
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
        private string $trivia = ' ',
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
        return '#' . $this->tag . $this->trivia . $this->form->getCode();
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
