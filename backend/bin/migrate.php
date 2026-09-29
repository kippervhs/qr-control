<?php

declare(strict_types=1);

use QrControl\Config;
use QrControl\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Config::load(dirname(__DIR__, 2) . '/.env');
$pdo = Database::connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB');
$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') as $file) {
    $version = basename($file);
    if (in_array($version, $applied, true)) continue;
    // MySQL confirma DDL implicitamente; cada arquivo deve ser idempotente.
    $pdo->exec((string) file_get_contents($file));
    $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    echo "Aplicada: {$version}\n";
}

if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn()) {
    $statement = $pdo->prepare("INSERT INTO users (id, username, code_prefix, role, password_hash, active) VALUES (UUID(), ?, UPPER(SUBSTRING(REGEXP_REPLACE(?, '[^A-Za-z0-9]', ''), 1, 3)), 'admin', ?, TRUE) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), active = TRUE, code_prefix = VALUES(code_prefix), role = 'admin'");
    $adminUsername = Config::get('ADMIN_USERNAME');
    $statement->execute([$adminUsername, $adminUsername, Config::get('ADMIN_PASSWORD_HASH')]);
    echo "Usuário administrativo sincronizado.\n";
}
