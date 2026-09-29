<?php
declare(strict_types=1);

namespace QrControl;

final class UserService
{
    public static function page(): array
    {
        $rows = Database::connection()->query('SELECT id, username, code_prefix, role, active, created_at, updated_at FROM users ORDER BY username')->fetchAll();
        return ['items' => array_map([self::class, 'format'], $rows)];
    }

    public static function create(array $body): array
    {
        $username = self::username($body['username'] ?? null);
        $password = self::password($body['password'] ?? null);
        $role = self::role($body['role'] ?? 'vendedor', Auth::role() === 'admin');
        $prefix = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $username), 0, 3));
        $pdo = Database::connection();
        try {
            $statement = $pdo->prepare('INSERT INTO users (id, username, code_prefix, role, password_hash, active) VALUES (UUID(), ?, ?, ?, ?, 1)');
            $statement->execute([$username, $prefix, $role, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if ((string) $e->errorInfo[1] === '1062') throw new HttpException(409, 'Usuário ou prefixo de código já está em uso.');
            throw $e;
        }
        return self::findByUsername($username);
    }

    public static function update(string $id, array $body): array
    {
        $pdo = Database::connection();
        $target = self::find($id);
        $actorRole = Auth::role();
        $isAdmin = $actorRole === 'admin';
        if (!$isAdmin && $target['role'] === 'admin') throw new HttpException(403, 'A manutenção não pode alterar o administrador.');
        if (array_key_exists('role', $body)) {
            $newRole = self::role($body['role'], $isAdmin);
            if ($target['id'] === Auth::userId() && $newRole !== 'admin') throw new HttpException(409, 'O administrador atual não pode remover a própria classe.');
            if ($target['role'] === 'admin' && $newRole !== 'admin') self::ensureAnotherAdmin($target['id']);
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $id]);
        }
        if (array_key_exists('active', $body)) {
            $active = filter_var($body['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === null) throw new HttpException(400, 'Status inválido.');
            if (!$active && $target['id'] === Auth::userId()) throw new HttpException(409, 'Você não pode inativar a própria conta.');
            if (!$active && $target['role'] === 'admin') self::ensureAnotherAdmin($target['id']);
            $pdo->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
        }
        return self::find($id);
    }

    public static function delete(string $id): array
    {
        $target = self::find($id);
        if ($target['role'] === 'admin') throw new HttpException(403, 'A conta Administrador não pode ser excluída.');
        if ($target['id'] === Auth::userId()) throw new HttpException(409, 'Você não pode excluir a própria conta.');
        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM batches WHERE user_id = ?');
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) throw new HttpException(409, 'Este usuário possui QR Codes. Inative a conta em vez de excluí-la.');
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        return ['ok' => true];
    }

    private static function find(string $id): array
    {
        $q = Database::connection()->prepare('SELECT id, username, code_prefix, role, active, created_at, updated_at FROM users WHERE id = ?');
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row) throw new HttpException(404, 'Usuário não encontrado.');
        return self::format($row);
    }

    private static function findByUsername(string $username): array
    {
        $q = Database::connection()->prepare('SELECT id, username, code_prefix, role, active, created_at, updated_at FROM users WHERE username = ?');
        $q->execute([$username]);
        return self::format($q->fetch());
    }

    private static function ensureAnotherAdmin(string $id): void
    {
        $q = Database::connection()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?");
        $q->execute([$id]);
        if ((int) $q->fetchColumn() < 1) throw new HttpException(409, 'É necessário manter pelo menos um administrador ativo.');
    }

    private static function username(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (!preg_match('/^[A-Za-z0-9_]{3,100}$/', $value)) throw new HttpException(400, 'Usuário: use de 3 a 100 caracteres, apenas letras, números e _.');
        return strtolower($value);
    }

    private static function password(mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 8 || strlen($value) > 200) throw new HttpException(400, 'A senha precisa ter entre 8 e 200 caracteres.');
        return $value;
    }

    private static function role(mixed $value, bool $adminAllowed): string
    {
        $value = is_string($value) ? $value : '';
        if (!$adminAllowed && !in_array($value, ['vendedor', 'manutencao'], true)) throw new HttpException(403, 'A manutenção só pode criar usuários Vendedor ou Manutenção.');
        if (!in_array($value, ['admin', 'vendedor', 'manutencao'], true)) throw new HttpException(400, 'Classe de usuário inválida.');
        return $value;
    }

    private static function format(array $row): array
    {
        return ['id' => $row['id'], 'username' => $row['username'], 'codePrefix' => $row['code_prefix'], 'role' => $row['role'], 'active' => (bool) $row['active'], 'createdAt' => $row['created_at'], 'updatedAt' => $row['updated_at']];
    }
}
