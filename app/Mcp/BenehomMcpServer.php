<?php

declare(strict_types=1);

namespace Hiram9112\Benehom\Mcp;

require_once dirname(__DIR__) . '/models/McpPersonalAccessToken.php';

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\Tool;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BenehomMcpServer
{
    /** @var list<string> */
    private array $allowedHosts;

    /** @var non-empty-string */
    private string $sessionDirectory;

    private McpFinancialDataToolAdapter $financialDataTool;

    /** @var \Closure(string): ?int */
    private \Closure $authenticatePat;

    private McpAuthenticatedUserContext $authenticatedUser;

    /**
     * @param list<string>|null $allowedHosts
     */
    public function __construct(
        ?string $sessionDirectory = null,
        ?array $allowedHosts = null,
        ?\NumaFinancialToolRegistryInterface $financialTools = null,
        ?callable $authenticatePat = null,
    ) {
        $this->sessionDirectory = $sessionDirectory ?? rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/benehom-mcp-sessions';
        $this->allowedHosts = $allowedHosts ?? $this->allowedHosts();
        $this->authenticatedUser = new McpAuthenticatedUserContext();
        $this->financialDataTool = new McpFinancialDataToolAdapter($financialTools ?? new \NumaFinancialToolRegistry(), $this->authenticatedUser);
        $this->authenticatePat = \Closure::fromCallable($authenticatePat ?? [\McpPersonalAccessToken::class, 'authenticate']);
    }

    public function emitFromGlobals(): void
    {
        $factory = new Psr17Factory();
        $request = (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();

        (new SapiEmitter())->emit($this->handle($request));
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $definition = (new \NumaFinancialDataToolContract())->functionDeclaration();
        $server = Server::builder()
            ->setServerInfo('BeneHom MCP', '0.1.0', 'Servidor MCP de BeneHom.')
            ->setCapabilities(new ServerCapabilities(
                tools: true,
                toolsListChanged: false,
                resources: false,
                resourcesSubscribe: false,
                resourcesListChanged: false,
                prompts: false,
                promptsListChanged: false,
                logging: false,
                completions: false,
            ))
            ->setSession(new FileSessionStore($this->sessionDirectory))
            ->add(new Tool(
                name: $definition['name'],
                title: null,
                inputSchema: $definition['parameters'],
                description: $definition['description']
                    . ' Los periodos deben enviarse en formato canónico YYYY-MM; no interpreta expresiones temporales en lenguaje natural.',
                annotations: null,
            ), $this->financialDataTool)
            ->build();

        return $server->run(new StreamableHttpTransport(
            $request,
            middleware: [
                new CorsMiddleware(),
                new DnsRebindingProtectionMiddleware($this->allowedHosts),
                new McpPatAuthenticationMiddleware($this->authenticatePat, $this->authenticatedUser, new Psr17Factory()),
            ],
        ));
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $hosts = ['localhost', '127.0.0.1', '[::1]'];
        $configuredUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        $configuredHost = parse_url((string) $configuredUrl, PHP_URL_HOST);

        if (is_string($configuredHost) && $configuredHost !== '') {
            $hosts[] = strtolower($configuredHost);
        }

        return array_values(array_unique($hosts));
    }
}
