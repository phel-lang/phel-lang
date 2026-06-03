<?php

declare(strict_types=1);

namespace PhelTest\Fixtures\Reflection;

/**
 * Carries attributes at class, method, and property level so the Phel
 * reflection helpers can read each back.
 */
#[Route('/products', method: 'GET')]
final class AnnotatedController
{
    #[Route('/products/{id}')]
    public string $id = '';

    #[Route('/products', method: 'POST')]
    public function create(): string
    {
        return $this->id;
    }
}
