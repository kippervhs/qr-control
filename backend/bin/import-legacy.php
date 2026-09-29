<?php

declare(strict_types=1);

use QrControl\Config;
use QrControl\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
Config::load(dirname(__DIR__, 2) . '/.env');
$pdo = Database::connection();
$data = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/database/legacy-data.json'), true, flags: JSON_THROW_ON_ERROR);
if ((int) $pdo->query('SELECT COUNT(*) FROM batches')->fetchColumn() > 0) {
    throw new RuntimeException('Importação cancelada: o MySQL já contém lotes.');
}
$admin = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' AND active = TRUE LIMIT 1");
$admin->execute();
$adminId = $admin->fetchColumn();
if (!$adminId) throw new RuntimeException('Usuário admin não encontrado. Rode as migrations antes da importação.');
$pdo->beginTransaction();
try {
    $batchInsert = $pdo->prepare('INSERT INTO batches (id, user_id, start_code, end_code, quantity, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $qrInsert = $pdo->prepare('INSERT INTO qr_codes (id, code, destination_url, batch_id, user_id, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $qrCount = 0;
    foreach ($data['batches'] as $batch) {
        $batchInsert->execute([$batch['id'], $adminId, $batch['startCode'], $batch['endCode'], $batch['quantity'], $batch['createdAt']]);
        foreach ($batch['qrCodes'] as $qr) {
            $qrInsert->execute([$qr['id'], $qr['code'], $qr['destinationUrl'], $batch['id'], $adminId, $qr['active'] ? 1 : 0, $qr['createdAt'], $qr['updatedAt']]);
            $qrCount++;
        }
    }
    $pdo->prepare('INSERT INTO qr_code_sequences (user_id, next_number) VALUES (?, ?) ON DUPLICATE KEY UPDATE next_number = VALUES(next_number)')->execute([$adminId, $data['nextCode']]);
    $pdo->commit();
    echo count($data['batches']) . " lotes e {$qrCount} QR Codes importados. Próximo código: {$data['nextCode']}.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
