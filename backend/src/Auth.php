<?php

declare(strict_types=1);

namespace QrControl;

use PDO;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name('qr_admin_session');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 12,
            'path' => '/',
            'secure' => Config::bool('SESSION_SECURE', str_starts_with(strtolower(Config::appUrl()), 'https://')),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function session(): array
    {
        self::startSession();
        if (!isset($_SESSION['user_id'])) return ['authenticated' => false, 'username' => null, 'role' => null, 'csrfToken' => $_SESSION['csrf']];
        $user = self::currentUser();
        if (!$user || !(bool) $user['active']) {
            $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
            return ['authenticated' => false, 'username' => null, 'role' => null, 'csrfToken' => $_SESSION['csrf']];
        }
        return ['authenticated' => true, 'username' => $user['username'], 'role' => $user['role'], 'csrfToken' => $_SESSION['csrf']];
    }

    public static function requireAdmin(): void
    {
        self::startSession();
        if (!self::currentUser()) throw new HttpException(401, 'Acesso não autorizado.');
    }

    public static function requireQrManager(): void
    {
        self::requireAdmin();
        if (!in_array(self::role(), ['admin', 'vendedor'], true)) throw new HttpException(403, 'Sua classe não possui acesso aos QR Codes.');
    }

    public static function requireUserManager(): void
    {
        self::requireAdmin();
        if (!in_array(self::role(), ['admin', 'manutencao'], true)) throw new HttpException(403, 'Sua classe não possui acesso à gestão de usuários.');
    }

    public static function role(): string
    {
        self::requireAdmin();
        return (string) self::currentUser()['role'];
    }

    public static function currentUser(): ?array
    {
        self::startSession();
        if (!isset($_SESSION['user_id'])) return null;
        $query = Database::connection()->prepare('SELECT id, username, code_prefix, role, active FROM users WHERE id = ? LIMIT 1');
        $query->execute([$_SESSION['user_id']]);
        $user = $query->fetch();
        if (!$user || !(bool) $user['active']) return null;
        $_SESSION['admin'] = $user['username'];
        $_SESSION['user_prefix'] = $user['code_prefix'];
        $_SESSION['role'] = $user['role'];
        return $user; 
    }

    public static function userId(): string
    {
        self::requireAdmin();
        return (string) $_SESSION['user_id'];
    }

    public static function userPrefix(): string
    {
        self::requireAdmin();
        if (!empty($_SESSION['user_prefix'])) return (string) $_SESSION['user_prefix'];
        $query = Database::connection()->prepare('SELECT username, code_prefix FROM users WHERE id = ? AND active = TRUE LIMIT 1');
        $query->execute([$_SESSION['user_id']]);
        $user = $query->fetch();
        if (!$user) throw new HttpException(401, 'Usuário não encontrado.');
        $prefix = (string) ($user['code_prefix'] ?? '');
        if ($prefix === '') {
            $prefix = self::prefixFromUsername((string) $user['username']);
            $collision = Database::connection()->prepare('SELECT 1 FROM users WHERE code_prefix = ? AND id <> ? LIMIT 1');
            $collision->execute([$prefix, $_SESSION['user_id']]);
            if ($collision->fetchColumn()) throw new HttpException(409, 'Os três primeiros caracteres deste usuário já estão em uso como prefixo.');
            Database::connection()->prepare('UPDATE users SET code_prefix = ? WHERE id = ?')->execute([$prefix, $_SESSION['user_id']]);
        }
        $_SESSION['user_prefix'] = $prefix;
        return $prefix;
    }

    public static function requireCsrf(): void
    {
        self::startSession();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            throw new HttpException(403, 'Sessão inválida. Atualize a página e tente novamente.');
        }
    }

    public static function login(array $body): array
    {
        self::requireCsrf();
        $ipHash = hash('sha256', self::clientIp(), true);
        self::checkRateLimit($ipHash);
        $username = is_string($body['username'] ?? null) ? trim($body['username']) : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';
        $statement = Database::connection()->prepare('SELECT id, username, password_hash, code_prefix, role FROM users WHERE username = ? AND active = TRUE LIMIT 1');
        $statement->execute([$username]);
        $user = $statement->fetch();
        $validPassword = is_array($user) && password_verify($password, (string) $user['password_hash']);
        if (!$validPassword) {
            self::recordFailure($ipHash);
            throw new HttpException(401, 'Usuário ou senha incorretos.');
        }
        self::clearFailures($ipHash);
        $prefix = (string) ($user['code_prefix'] ?? '');
        if ($prefix === '') {
            $prefix = self::prefixFromUsername((string) $user['username']);
            $collision = Database::connection()->prepare('SELECT 1 FROM users WHERE code_prefix = ? AND id <> ? LIMIT 1');
            $collision->execute([$prefix, $user['id']]);
            if ($collision->fetchColumn()) throw new HttpException(409, 'Os três primeiros caracteres deste usuário já estão em uso como prefixo.');
            Database::connection()->prepare('UPDATE users SET code_prefix = ? WHERE id = ?')->execute([$prefix, $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['admin'] = (string) $user['username'];
        $_SESSION['user_prefix'] = $prefix;
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return self::session();
    }

    public static function logout(): void
    {
        self::requireAdmin();
        self::requireCsrf();
        $_SESSION = [];
        session_destroy();
    }

    private static function prefixFromUsername(string $username): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $username));
        $prefix = substr($clean, 0, 3);
        if (strlen($prefix) !== 3) throw new HttpException(400, 'O usuário precisa ter pelo menos três caracteres alfanuméricos.');
        return $prefix;
    }

    private static function clientIp(): string
    {
        if (Config::bool('TRUST_PROXY_HEADERS', false)) {
            $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) return $forwarded;
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function checkRateLimit(string $hash): void
    {
        $pdo = Database::connection();
        if (random_int(1, 100) === 1) $pdo->exec('DELETE FROM login_attempts WHERE reset_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
        $query = $pdo->prepare('SELECT blocked_until FROM login_attempts WHERE ip_hash = ?');
        $query->execute([$hash]);
        $blocked = $query->fetchColumn();
        if ($blocked && strtotime((string) $blocked) > time()) {
            throw new HttpException(429, 'Muitas tentativas. Aguarde alguns minutos.');
        }
    }

    private static function recordFailure(string $hash): void
    {
        Database::connection()->prepare(
            'INSERT INTO login_attempts (ip_hash, attempt_count, reset_at) VALUES (?, 1, UTC_TIMESTAMP() + INTERVAL 15 MINUTE)
             ON DUPLICATE KEY UPDATE attempt_count = IF(reset_at <= UTC_TIMESTAMP(), 1, attempt_count + 1), blocked_until = IF(reset_at <= UTC_TIMESTAMP(), NULL, IF(attempt_count >= 5, UTC_TIMESTAMP() + INTERVAL 15 MINUTE, NULL)), reset_at = IF(reset_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 15 MINUTE, reset_at)'
        )->execute([$hash]);
    }

    private static function clearFailures(string $hash): void
    {
        Database::connection()->prepare('DELETE FROM login_attempts WHERE ip_hash = ?')->execute([$hash]);
    }
}
