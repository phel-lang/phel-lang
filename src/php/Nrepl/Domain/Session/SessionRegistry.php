<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Session;

use function array_keys;
use function bin2hex;
use function random_bytes;

final class SessionRegistry
{
    /** @var array<string, Session> */
    private array $sessions = [];

    public function create(): Session
    {
        $id = $this->generateId();
        $session = new Session($id);
        $this->sessions[$id] = $session;

        return $session;
    }

    public function get(string $id): ?Session
    {
        return $this->sessions[$id] ?? null;
    }

    public function close(string $id): bool
    {
        if (!isset($this->sessions[$id])) {
            return false;
        }

        unset($this->sessions[$id]);

        return true;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->sessions);
    }

    private function generateId(): string
    {
        // UUID-like 32-hex string, stable format.
        return bin2hex(random_bytes(16));
    }
}
