<?php

declare(strict_types=1);

namespace QrControl;

use PDO;

final class BatchService
{
    public static function page(int $page, int $pageSize = 10): array
    {
        $pdo = Database::connection();
        $userId = Auth::userId();
        $count = $pdo->prepare('SELECT COUNT(*) FROM batches WHERE user_id = ?');
        $count->execute([$userId]);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $query = $pdo->prepare('SELECT id, start_code, end_code, quantity, created_at FROM batches WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
        $query->bindValue(1, $userId);
        $query->bindValue(2, $pageSize, PDO::PARAM_INT);
        $query->bindValue(3, $offset, PDO::PARAM_INT);
        $query->execute();
        $batches = $query->fetchAll();
        self::attachQrCodes($batches);
        return ['items' => array_map([self::class, 'formatBatch'], $batches), 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public static function find(string $id): array
    {
        $query = Database::connection()->prepare('SELECT id, start_code, end_code, quantity, created_at FROM batches WHERE id = ? AND user_id = ?');
        $query->execute([$id, Auth::userId()]);
        $batches = $query->fetchAll();
        if (!$batches) throw new HttpException(404, 'Lote não encontrado.');
        self::attachQrCodes($batches);
        return self::formatBatch($batches[0]);
    }

    public static function create(mixed $quantity): array
    {
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        if ($quantity === false) throw new HttpException(400, 'Crie entre 1 e 500 QR Codes.');
        $pdo = Database::connection();
        $userId = Auth::userId();
        $prefix = Auth::userPrefix();
        if (!preg_match('/^[A-Z0-9]{3}$/', $prefix)) throw new HttpException(500, 'Usuário sem prefixo de código configurado.');
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT IGNORE INTO qr_code_sequences (user_id, next_number) VALUES (?, 1)')->execute([$userId]);
            $statement = $pdo->prepare('SELECT next_number FROM qr_code_sequences WHERE user_id = ? FOR UPDATE');
            $statement->execute([$userId]);
            $start = (int) $statement->fetchColumn();
            $end = $start + $quantity - 1;
            if ($end > 1679615) throw new HttpException(409, 'A sequência de QR Codes deste usuário chegou ao limite.');
            $pdo->prepare('UPDATE qr_code_sequences SET next_number = ? WHERE user_id = ?')->execute([$end + 1, $userId]);
            $codes = [];
            for ($number = $start; $number <= $end; $number++) $codes[] = Support::sequenceCode($prefix, $number);
            $id = Support::uuid();
            $pdo->prepare('INSERT INTO batches (id, user_id, start_code, end_code, quantity) VALUES (?, ?, ?, ?, ?)')->execute([$id, $userId, $codes[0], $codes[array_key_last($codes)], $quantity]);
            foreach (array_chunk($codes, 100) as $chunk) {
                $values = [];
                $parameters = [];
                foreach ($chunk as $code) {
                    $values[] = '(?, ?, ?, ?)';
                    array_push($parameters, Support::uuid(), $code, $id, $userId);
                }
                $pdo->prepare('INSERT INTO qr_codes (id, code, batch_id, user_id) VALUES ' . implode(',', $values))->execute($parameters);
            }
            $pdo->commit();
            return ['id' => $id, 'startCode' => $codes[0], 'endCode' => $codes[array_key_last($codes)], 'quantity' => $quantity];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function delete(array $ids): array
    {
        $pdo = Database::connection();
        $userId = Auth::userId();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare("SELECT COUNT(*) AS batches, COALESCE(SUM(quantity), 0) AS codes FROM batches WHERE user_id = ? AND id IN ({$placeholders}) FOR UPDATE");
            $query->execute(array_merge([$userId], $ids));
            $counts = $query->fetch();
            if ((int) $counts['batches'] !== count($ids)) throw new HttpException(404, 'Um dos lotes selecionados não foi encontrado.');
            $pdo->prepare("DELETE FROM batches WHERE user_id = ? AND id IN ({$placeholders})")->execute(array_merge([$userId], $ids));
            $pdo->commit();
            return ['batchCount' => (int) $counts['batches'], 'qrCodeCount' => (int) $counts['codes']];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function exportRows(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = Database::connection()->prepare("SELECT b.id, b.start_code, b.end_code, q.code FROM batches b JOIN qr_codes q ON q.batch_id = b.id WHERE b.user_id = ? AND b.id IN ({$placeholders}) ORDER BY b.start_code, q.code");
        $query->execute(array_merge([Auth::userId()], $ids));
        $rows = $query->fetchAll();
        if (count(array_unique(array_column($rows, 'id'))) !== count($ids)) throw new HttpException(404, 'Um dos lotes selecionados não foi encontrado.');
        return $rows;
    }

    private static function attachQrCodes(array &$batches): void
    {
        if (!$batches) return;
        $byId = [];
        foreach ($batches as $index => $batch) {
            $batches[$index]['qrCodes'] = [];
            $byId[$batch['id']] = $index;
        }
        $ids = array_keys($byId);
        $query = Database::connection()->prepare('SELECT id, code, batch_id, destination_url, active, created_at, updated_at FROM qr_codes WHERE user_id = ? AND batch_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY code');
        $query->execute(array_merge([Auth::userId()], $ids));
        foreach ($query as $qr) {
            $batches[$byId[$qr['batch_id']]]['qrCodes'][] = [
                'id' => $qr['id'],
                'code' => Support::formatCode((string) $qr['code']),
                'destinationUrl' => $qr['destination_url'],
                'active' => (bool) $qr['active'],
                'createdAt' => $qr['created_at'],
                'updatedAt' => $qr['updated_at'],
                'batchId' => $qr['batch_id'],
            ];
        }
    }

    private static function formatBatch(array $batch): array
    {
        return [
            'id' => $batch['id'],
            'startCode' => Support::formatCode((string) $batch['start_code']),
            'endCode' => Support::formatCode((string) $batch['end_code']),
            'quantity' => (int) $batch['quantity'],
            'createdAt' => $batch['created_at'],
            'qrCodes' => $batch['qrCodes'],
        ];
    }
}
