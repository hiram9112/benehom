<?php

declare(strict_types=1);

namespace Tests\Integration;

use Hiram9112\Benehom\Mcp\BenehomMcpServer;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;

require_once APP_PATH . '/models/McpPersonalAccessToken.php';

final class McpPersonalAccessTokenTest extends IntegrationTestCase
{
    private string $mcpSessionDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
        $this->mcpSessionDirectory = sys_get_temp_dir() . '/benehom-mcp-integration-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        foreach (glob($this->mcpSessionDirectory . '/*') ?: [] as $session) {
            unlink($session);
        }

        if (is_dir($this->mcpSessionDirectory)) {
            rmdir($this->mcpSessionDirectory);
        }

        parent::tearDown();
    }

    public function testCreaUnPatSinPersistirElSecretoEnClaroYAutenticaAUnUsuario(): void
    {
        $usuario = $this->crearUsuario('mcp-pat@example.test');
        $created = \McpPersonalAccessToken::create((int) $usuario['id'], 'Cliente de pruebas');

        self::assertMatchesRegularExpression('/^bhmcp_[a-f0-9]{32}_[a-f0-9]{64}$/', $created['token']);
        [, $selector, $secret] = explode('_', $created['token'], 3);
        self::assertSame(32, strlen($selector));
        self::assertSame(64, strlen($secret));
        self::assertSame(16, strlen(hex2bin($selector) ?: ''));
        self::assertSame(32, strlen(hex2bin($secret) ?: ''));

        $stored = $this->db->query(
            'SELECT id, usuario_id, nombre, selector, secret_hash, created_at, last_used_at, revoked_at
             FROM mcp_personal_access_tokens WHERE id = ' . (int) $created['id']
        )->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($stored);
        self::assertSame((int) $usuario['id'], (int) $stored['usuario_id']);
        self::assertSame($selector, $stored['selector']);
        self::assertNull($stored['last_used_at']);
        $persistedRecord = json_encode($stored, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($created['token'], $persistedRecord);
        self::assertStringNotContainsString($secret, $persistedRecord);
        self::assertTrue(password_verify($secret, (string) $stored['secret_hash']));

        self::assertSame((int) $usuario['id'], \McpPersonalAccessToken::authenticate($created['token']));
        self::assertNotNull($this->db->query(
            'SELECT last_used_at FROM mcp_personal_access_tokens WHERE id = ' . (int) $created['id']
        )->fetchColumn());
    }

    public function testGeneraPatDistintosYResuelveLaIdentidadDeCadaPropietario(): void
    {
        $firstUser = $this->crearUsuario('mcp-first-user@example.test');
        $secondUser = $this->crearUsuario('mcp-second-user@example.test');
        $firstToken = \McpPersonalAccessToken::create((int) $firstUser['id'], 'Primer cliente');
        $secondToken = \McpPersonalAccessToken::create((int) $secondUser['id'], 'Segundo cliente');

        self::assertSame((int) $firstUser['id'], \McpPersonalAccessToken::authenticate($firstToken['token']));
        self::assertSame((int) $secondUser['id'], \McpPersonalAccessToken::authenticate($secondToken['token']));
    }

    public function testPatRevocadoDejaDeAutorizarNuevasPeticionesMcp(): void
    {
        $usuario = $this->crearUsuario('mcp-server-revoked@example.test');
        $created = \McpPersonalAccessToken::create((int) $usuario['id'], 'Servidor MCP');
        $server = new BenehomMcpServer($this->mcpSessionDirectory, ['benehom.test']);

        $initialize = $server->handle($this->mcpRequest([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                'capabilities' => new \stdClass(),
            ],
        ], $created['token']));
        self::assertSame(200, $initialize->getStatusCode());
        $sessionId = $initialize->getHeaderLine('Mcp-Session-Id');
        self::assertNotSame('', $sessionId);

        $initialized = $server->handle($this->mcpRequest([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], $created['token'], $sessionId));
        self::assertSame(202, $initialized->getStatusCode());

        $beforeRevocation = $server->handle($this->mcpRequest([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => new \stdClass(),
        ], $created['token'], $sessionId));
        self::assertSame(200, $beforeRevocation->getStatusCode());

        self::assertTrue(\McpPersonalAccessToken::revoke((int) $created['id'], (int) $usuario['id']));

        $afterRevocation = $server->handle($this->mcpRequest([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/list',
            'params' => new \stdClass(),
        ], $created['token'], $sessionId));
        self::assertSame(401, $afterRevocation->getStatusCode());
        self::assertSame('Bearer', $afterRevocation->getHeaderLine('WWW-Authenticate'));
    }

    public function testRechazaTokenMalFormadoSinModificarUltimoUso(): void
    {
        $usuario = $this->crearUsuario('mcp-invalid@example.test');
        $created = \McpPersonalAccessToken::create((int) $usuario['id'], 'Cliente de pruebas');

        self::assertNull(\McpPersonalAccessToken::authenticate('Bearer ' . $created['token']));
        $this->assertLastUsedAtIsNull((int) $created['id']);
    }

    public function testRechazaSelectorDesconocido(): void
    {
        $token = 'bhmcp_' . str_repeat('f', 32) . '_' . str_repeat('a', 64);

        self::assertNull(\McpPersonalAccessToken::authenticate($token));
    }

    public function testRechazaSecretoIncorrectoSinModificarUltimoUso(): void
    {
        $usuario = $this->crearUsuario('mcp-wrong-secret@example.test');
        $created = \McpPersonalAccessToken::create((int) $usuario['id'], 'Cliente de pruebas');
        $lastCharacter = substr($created['token'], -1);
        $incorrect = substr($created['token'], 0, -1) . ($lastCharacter === '0' ? '1' : '0');

        self::assertNull(\McpPersonalAccessToken::authenticate($incorrect));
        $this->assertLastUsedAtIsNull((int) $created['id']);
    }

    public function testRechazaPatRevocadoSinModificarUltimoUso(): void
    {
        $usuario = $this->crearUsuario('mcp-revoked@example.test');
        $created = \McpPersonalAccessToken::create((int) $usuario['id'], 'Cliente revocado');

        self::assertTrue(\McpPersonalAccessToken::revoke((int) $created['id'], (int) $usuario['id']));
        self::assertNull(\McpPersonalAccessToken::authenticate($created['token']));
        $this->assertLastUsedAtIsNull((int) $created['id']);
    }

    public function testPatCompletoNoSeGuardaEnSesionNiPuedeVolverAMostrarse(): void
    {
        $usuario = $this->crearUsuario('mcp-one-time@example.test');
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario'] = 'Usuario test';
        $_POST = ['nombre' => 'Cliente de un solo uso'];

        require_once APP_PATH . '/controllers/CuentaController.php';
        $controller = new \CuentaController();

        ob_start();
        $controller->crearTokenMcp();
        $creationHtml = (string) ob_get_clean();
        self::assertMatchesRegularExpression('/bhmcp_[a-f0-9]{32}_[a-f0-9]{64}/', $creationHtml);
        preg_match('/bhmcp_[a-f0-9]{32}_[a-f0-9]{64}/', $creationHtml, $matches);
        $completeToken = $matches[0];

        self::assertStringNotContainsString($completeToken, serialize($_SESSION));

        ob_start();
        $controller->index();
        $laterHtml = (string) ob_get_clean();
        self::assertStringNotContainsString($completeToken, $laterHtml);
        self::assertStringContainsString('Cliente de un solo uso', $laterHtml);
    }

    public function testListaYRevocaSoloLosTokensDelUsuarioPropietario(): void
    {
        $owner = $this->crearUsuario('mcp-owner@example.test');
        $other = $this->crearUsuario('mcp-other@example.test');
        $ownerToken = \McpPersonalAccessToken::create((int) $owner['id'], 'Token propietario');
        \McpPersonalAccessToken::create((int) $other['id'], 'Token ajeno');

        $tokens = \McpPersonalAccessToken::listForUser((int) $owner['id']);
        self::assertCount(1, $tokens);
        self::assertSame('Token propietario', $tokens[0]['nombre']);
        self::assertFalse(\McpPersonalAccessToken::revoke((int) $ownerToken['id'], (int) $other['id']));
        self::assertTrue(\McpPersonalAccessToken::revoke((int) $ownerToken['id'], (int) $owner['id']));
        self::assertFalse(\McpPersonalAccessToken::revoke((int) $ownerToken['id'], (int) $owner['id']));
        self::assertNull(\McpPersonalAccessToken::authenticate($ownerToken['token']));
    }

    private function assertLastUsedAtIsNull(int $tokenId): void
    {
        self::assertNull($this->db->query(
            'SELECT last_used_at FROM mcp_personal_access_tokens WHERE id = ' . $tokenId
        )->fetchColumn());
    }

    /** @param array<string, mixed> $payload */
    private function mcpRequest(array $payload, string $token, ?string $sessionId = null): ServerRequest
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Host' => 'benehom.test',
        ];

        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
            $headers['Mcp-Protocol-Version'] = '2025-11-25';
        }

        return new ServerRequest(
            'POST',
            'https://benehom.test/mcp',
            $headers,
            Stream::create(json_encode($payload, JSON_THROW_ON_ERROR)),
        );
    }
}
