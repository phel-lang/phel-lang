<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use WeakMap;

/**
 * @internal
 */
final readonly class SemanticViews
{
    /** @var WeakMap<object, mixed> */
    private WeakMap $views;

    public function __construct()
    {
        $this->views = new WeakMap();
    }

    public function get(InnerNodeInterface $source): ?InnerNodeInterface
    {
        $view = $this->views[$source] ?? null;
        return $view instanceof InnerNodeInterface ? $view : null;
    }

    public function put(InnerNodeInterface $source, InnerNodeInterface $view): void
    {
        $this->views->offsetSet($source, $view);
    }
}
