<?php

declare(strict_types=1);

namespace QrControl;

final class QrService
{
    public static function page(int $page, int $pageSize, string $query = ''): array
    {
        $userId = Auth::userId();
        $normalized = $query === '' ? null : Support::normalizeCode($query);
        $where = $query === '' ? ' WHERE q.user_id = ?' : ' WHERE q.user_id = ? AND q.code = ?';
        $params = $query === '' ? [$userId] : [$userId, $normalized ?? ''];
        $count = Database::connection()->prepare('SELECT COUNT(*) FROM qr_codes q' . $where);
        $count->execute($params);
        $offset = ($page - 1) * $pageSize;
        $statement = Database::connection()->prepare('SELECT q.*, b.start_code, b.end_code FROM qr_codes q JOIN batches b ON b.id = q.batch_id' . $where . ' ORDER BY q.code DESC LIMIT ? OFFSET ?');
        foreach ($params as $index => $value) $statement->bindValue($index + 1, $value);
        $statement->bindValue(count($params) + 1, $pageSize, \PDO::PARAM_INT);
        $statement->bindValue(count($params) + 2, $offset, \PDO::PARAM_INT);
        $statement->execute();
        return ['items' => array_map([self::class, 'format'], $statement->fetchAll()), 'total' => (int) $count->fetchColumn(), 'page' => $page, 'pageSize' => $pageSize];
    }

    public static function find(string $code): array
    {
        $query = Database::connection()->prepare('SELECT q.*, b.start_code, b.end_code FROM qr_codes q JOIN batches b ON b.id = q.batch_id WHERE q.code = ? AND q.user_id = ?');
        $query->execute([$code, Auth::userId()]);
        $qr = $query->fetch();
        if (!$qr) throw new HttpException(404, 'QR Code não encontrado.');
        return self::format($qr);
    }

    public static function suggestions(string $query): array
    {
        $term = strtoupper(trim($query));
        if ($term === '' || !preg_match('/^[A-Z0-9-]{1,24}$/', $term)) return [];
        $normalized = Support::normalizeCode($term);
        $statement = Database::connection()->prepare("SELECT code, destination_url, active FROM qr_codes WHERE user_id = ? AND UPPER(code) LIKE ? ORDER BY CASE WHEN ? IS NOT NULL AND code = ? THEN 0 ELSE 1 END, code LIMIT 6");
        $pattern = '%' . $term . '%';
        $statement->execute([Auth::userId(), $pattern, $normalized, $normalized]);
        return array_map(static fn(array $row) => ['code' => Support::formatCode((string) $row['code']), 'destinationUrl' => $row['destination_url'], 'active' => (bool) $row['active']], $statement->fetchAll());
    }

    public static function update(string $code, array $body): array
    {
        $destination = Support::destinationUrl($body['destinationUrl'] ?? null);
        if (!is_bool($body['active'] ?? null)) throw new HttpException(400, 'Status inválido.');
        $query = Database::connection()->prepare('UPDATE qr_codes SET destination_url = ?, active = ?, updated_at = UTC_TIMESTAMP(6) WHERE code = ? AND user_id = ?');
        $query->execute([$destination, $body['active'] ? 1 : 0, $code, Auth::userId()]);
        if (!$query->rowCount()) self::find($code);
        return self::find($code);
    }

    public static function register(string $code, array $body): array
    {
        $destination = Support::destinationUrl($body['destinationUrl'] ?? null);
        $query = Database::connection()->prepare('UPDATE qr_codes SET destination_url = ?, active = 1, updated_at = UTC_TIMESTAMP(6) WHERE code = ? AND user_id = ? AND destination_url IS NULL');
        $query->execute([$destination, $code, Auth::userId()]);
        if (!$query->rowCount()) {
            $existing = self::find($code);
            throw new HttpException(409, $existing['destinationUrl'] ? 'Este QR Code já foi cadastrado. Atualize a página para continuar.' : 'Não foi possível cadastrar o destino.');
        }
        return ['code' => Support::formatCode($code), 'destinationUrl' => $destination];
    }

    public static function redirect(string $code): array
    {
        $query = Database::connection()->prepare('SELECT destination_url, active FROM qr_codes WHERE code = ?');
        $query->execute([$code]);
        $qr = $query->fetch();
        if (!$qr) throw new HttpException(404, 'QR Code não encontrado.');
        if (!(bool) $qr['active']) throw new HttpException(410, 'QR Code inativo.');
        return ['destinationUrl' => $qr['destination_url']];
    }

    public static function format(array $qr): array
    {
        return [
            'id' => $qr['id'], 'code' => Support::formatCode((string) $qr['code']),
            'destinationUrl' => $qr['destination_url'], 'active' => (bool) $qr['active'],
            'batchId' => $qr['batch_id'], 'createdAt' => $qr['created_at'], 'updatedAt' => $qr['updated_at'],
            'batch' => ['id' => $qr['batch_id'], 'startCode' => Support::formatCode((string) $qr['start_code']), 'endCode' => Support::formatCode((string) $qr['end_code'])],
        ];
    }
}
