<?php

declare(strict_types=1);

namespace Hiram9112\Benehom\Mcp;

final class McpAuthenticatedUserContext
{
    private ?int $authenticatedUserId = null;

    public function set(int $authenticatedUserId): void
    {
        if ($authenticatedUserId <= 0) {
            throw new \InvalidArgumentException('Identidad MCP no válida.');
        }

        $this->authenticatedUserId = $authenticatedUserId;
    }

    public function authenticatedUserId(): ?int
    {
        return $this->authenticatedUserId;
    }

    public function clear(): void
    {
        $this->authenticatedUserId = null;
    }
}
