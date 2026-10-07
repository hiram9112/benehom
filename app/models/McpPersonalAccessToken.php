<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class McpPersonalAccessToken
{
    private const PREFIX = 'bhmcp_';
    private const SELECTOR_BYTES = 16;
    private const SECRET_BYTES = 32;

    /**
     * @return array{
     *     id: int,
     *     token: string,
     *     record: array{id: int, nombre: string, last_used_at: null, revoked_at: null}
     * }
     */
    public static function create(int $usuarioId, string $nombre): array
    {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario no válido.');
        }

        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 100) {
            throw new InvalidArgumentException('Nombre no válido.');
        }

        $selector = bin2hex(random_bytes(self::SELECTOR_BYTES));
        $secret = bin2hex(random_bytes(self::SECRET_BYTES));
        $secretHash = password_hash($secret, PASSWORD_DEFAULT);

        if ($secretHash === false) {
            throw new RuntimeException('No se ha podido proteger el token.');
        }

        $connection = Database::getConnection();
        $statement = $connection->prepare(
            'INSERT INTO mcp_personal_access_tokens (usuario_id, nombre, selector, secret_hash)
             VALUES (:usuario_id, :nombre, :selector, :secret_hash)'
        );
        $statement->execute([
            ':usuario_id' => $usuarioId,
            ':nombre' => $nombre,
            ':selector' => $selector,
            ':secret_hash' => $secretHash,
        ]);

        $id = (int) $connection->lastInsertId();

        return [
            'id' => $id,
            'token' => self::PREFIX . $selector . '_' . $secret,
            'record' => [
                'id' => $id,
                'nombre' => $nombre,
                'last_used_at' => null,
                'revoked_at' => null,
            ],
        ];
    }

    /**
     * @return list<array{id: int, nombre: string, created_at: string, last_used_at: ?string, revoked_at: ?string}>
     */
    public static function listForUser(int $usuarioId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id, nombre, created_at, last_used_at, revoked_at
             FROM mcp_personal_access_tokens
             WHERE usuario_id = :usuario_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([':usuario_id' => $usuarioId]);

        /** @var list<array{id: int, nombre: string, created_at: string, last_used_at: ?string, revoked_at: ?string}> $tokens */
        $tokens = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $tokens;
    }

    public static function revoke(int $id, int $usuarioId): bool
    {
        if ($id <= 0 || $usuarioId <= 0) {
            return false;
        }

        $statement = Database::getConnection()->prepare(
            'UPDATE mcp_personal_access_tokens
             SET revoked_at = NOW()
             WHERE id = :id AND usuario_id = :usuario_id AND revoked_at IS NULL'
        );
        $statement->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId,
        ]);

        return $statement->rowCount() === 1;
    }

    public static function authenticate(string $token): ?int
    {
        if (!preg_match('/^bhmcp_([a-f0-9]{32})_([a-f0-9]{64})$/D', $token, $matches)) {
            return null;
        }

        $statement = Database::getConnection()->prepare(
            'SELECT id, usuario_id, secret_hash
             FROM mcp_personal_access_tokens
             WHERE selector = :selector AND revoked_at IS NULL
             LIMIT 1'
        );
        $statement->execute([':selector' => $matches[1]]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($record) || !password_verify($matches[2], (string) $record['secret_hash'])) {
            return null;
        }

        $lastUsed = Database::getConnection()->prepare(
            'UPDATE mcp_personal_access_tokens SET last_used_at = NOW() WHERE id = :id'
        );
        $lastUsed->execute([':id' => (int) $record['id']]);

        return (int) $record['usuario_id'];
    }
}
