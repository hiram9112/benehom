<?php

declare(strict_types=1);

namespace Hiram9112\Benehom\Mcp;

require_once dirname(__DIR__) . '/services/NumaFinancialTools.php';

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

final class McpFinancialDataToolAdapter implements ToolHandlerInterface
{
    public function __construct(
        private readonly \NumaFinancialToolRegistryInterface $financialTools,
        private readonly ?int $authenticatedUserId,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments, ClientGateway $gateway): mixed
    {
        if ($this->authenticatedUserId === null || $this->authenticatedUserId <= 0) {
            return $this->error('No se ha podido autenticar la consulta financiera.');
        }

        try {
            return $this->financialTools->execute(
                \NumaFinancialDataToolContract::NAME,
                $this->authenticatedUserId,
                $arguments,
            );
        } catch (\NumaFinancialToolInputIncomplete | \InvalidArgumentException) {
            return $this->error('Los argumentos de la consulta financiera no son válidos.');
        } catch (\NumaFinancialToolLimitExceeded) {
            return $this->error('No hemos podido procesar la consulta financiera.');
        } catch (\Throwable) {
            return $this->error('No hemos podido procesar la consulta financiera.');
        }
    }

    private function error(string $message): CallToolResult
    {
        return CallToolResult::error([new TextContent($message)]);
    }
}
