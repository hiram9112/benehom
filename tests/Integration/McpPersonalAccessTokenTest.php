<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/McpPersonalAccessToken.php';

final class McpPersonalAccessTokenTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        parent::tearDown();
    }

    public function testCreaUnPatSinPersistirElSecretoEnClaroYAutenticaAUnUsuario(): void
    {
        $usuario = $this->crearUsuario('mcp-pat@example.test');
        $created = \McpPersonalAccessToken::create((int) $usuario['id'], 'Cliente de pruebas');

        self::assertMatchesRegularExpression('/^bhmcp_[a-f0-9]{32}_[a-f0-9]{64}$/', $created['token']);

        $stored = $this->db->query(
            'SELECT usuario_id, selector, secret_hash, last_used_at FROM mcp_personal_access_tokens WHERE id = ' . (int) $created['id']
        )->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($stored);
        self::assertSame((int) $usuario['id'], (int) $stored['usuario_id']);
        self::assertNull($stored['last_used_at']);
        self::assertStringNotContainsString($created['token'], $stored['secret_hash']);
        self::assertStringNotContainsString(explode('_', $created['token'], 3)[2], $stored['secret_hash']);

        self::assertSame((int) $usuario['id'], \McpPersonalAccessToken::authenticate($created['token']));
        self::assertNotNull($this->db->query(
            'SELECT last_used_at FROM mcp_personal_access_tokens WHERE id = ' . (int) $created['id']
        )->fetchColumn());
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
}
