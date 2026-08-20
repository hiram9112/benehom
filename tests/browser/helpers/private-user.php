<?php

declare(strict_types=1);

$action = $argv[1] ?? '';
$email = $argv[2] ?? '';
$environment = getenv('APP_ENV') ?: '';
$databaseName = getenv('DB_NAME') ?: '';

if ($environment !== 'testing' || !str_ends_with($databaseName, '_test')) {
    fwrite(STDERR, "El fixture de navegador requiere APP_ENV=testing y una base de datos _test.\n");
    exit(1);
}

if (!in_array($action, ['create', 'delete'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: private-user.php create|delete email\n");
    exit(1);
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1',
        getenv('DB_PORT') ?: '3306',
        $databaseName
    ),
    getenv('DB_USER') ?: '',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

if ($action === 'delete') {
    $statement = $pdo->prepare('DELETE FROM usuarios WHERE email = :email');
    $statement->execute(['email' => $email]);
    echo json_encode(['email' => $email]);
    exit;
}

$schema = file_get_contents(dirname(__DIR__, 3) . '/database/schema.sql');

if ($schema === false) {
    throw new RuntimeException('No se pudo cargar el esquema de pruebas.');
}

foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    if (!preg_match('/^CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $matches)) {
        continue;
    }

    $table = $matches[1];
    $exists = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

    if ($exists !== false && $exists->fetchColumn() !== false) {
        continue;
    }

    $pdo->exec($statement);
}

$statement = $pdo->prepare(
    'INSERT INTO usuarios (usuario, email, password, email_verificado_en)
     VALUES (:usuario, :email, :password, NOW())'
);
$statement->execute([
    'usuario' => 'Usuario Playwright',
    'email' => $email,
    'password' => password_hash('Password-test-123', PASSWORD_BCRYPT),
]);

echo json_encode([
    'email' => $email,
    'password' => 'Password-test-123',
]);
