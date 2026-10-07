<?php

declare(strict_types=1);

require_once BASE_PATH . '/vendor/autoload.php';

use Hiram9112\Benehom\Mcp\BenehomMcpServer;

final class McpController
{
    public function server(): void
    {
        (new BenehomMcpServer())->emitFromGlobals();
    }
}
