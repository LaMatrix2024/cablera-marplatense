<?php
declare(strict_types=1);

require_once __DIR__ . '/HttpError.php';
require_once __DIR__ . '/CorporateInvitationService.php';

final class CentralHostingerTokenService
{
    private const TTL_HOURS = 8;
    public function __construct(private PDO $pdo) {}

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') throw new HttpError(401, 'El correo o la contraseña no son correctos.', 'INVALID_CREDENTIALS');
        $this->rateLimit($email);
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1'); $stmt->execute(['email' => $email]); $user = $stmt->fetch();
        $valid = is_array($user) && is_string($user['password_hash'] ?? null) && password_verify($password, $user['password_hash']);
        if (!$valid) { $this->registerFailure($email); throw new HttpError(401, 'El correo o la contraseña no son correctos.', 'INVALID_CREDENTIALS'); }
        if (($user['estado'] ?? '') !== 'ACTIVO') throw new HttpError(403, 'La cuenta no está activa.', 'INACTIVE_USER');
        $this->clearFailures($email);
        return $this->issue($user);
    }

    public function session(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new HttpError(401, 'Sesión no válida.', 'INVALID_TOKEN');
        $stmt = $this->pdo->prepare('SELECT s.*, s.sesion_version AS session_version, u.id AS user_id, u.estado, u.sesion_version AS user_session_version FROM auth_sesiones s INNER JOIN usuarios u ON u.id = s.usuario_id WHERE s.token_hash = :token AND s.revoked_at IS NULL AND s.expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => hash('sha256', $token)]); $row = $stmt->fetch();
        if (!is_array($row) || (int)$row['session_version'] !== (int)$row['user_session_version'] || ($row['estado'] ?? '') !== 'ACTIVO') throw new HttpError(401, 'Sesión no válida.', 'INVALID_TOKEN');
        $this->pdo->prepare('UPDATE auth_sesiones SET last_used_at = NOW() WHERE id = :id')->execute(['id' => $row['id']]);
        return ['profile' => $this->profileWithPermissions((int)$row['user_id']), 'expires_at' => $row['expires_at']];
    }

    public function logout(string $token): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token)) $this->pdo->prepare('UPDATE auth_sesiones SET revoked_at = COALESCE(revoked_at, NOW()) WHERE token_hash = :token')->execute(['token' => hash('sha256', $token)]);
    }

    private function issue(array $user): array
    {
        $token = bin2hex(random_bytes(32)); $version = (int)($user['sesion_version'] ?? 1);
        $stmt = $this->pdo->prepare('INSERT INTO auth_sesiones (usuario_id, token_hash, csrf_hash, sesion_version, expires_at, ip_address, user_agent) VALUES (:uid, :token, :csrf, :version, DATE_ADD(NOW(), INTERVAL ' . self::TTL_HOURS . ' HOUR), :ip, :ua)');
        $stmt->execute(['uid' => (int)$user['id'], 'token' => hash('sha256', $token), 'csrf' => hash('sha256', bin2hex(random_bytes(32))), 'version' => $version, 'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, 'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null]);
        $expires = $this->pdo->query('SELECT DATE_FORMAT(DATE_ADD(NOW(), INTERVAL ' . self::TTL_HOURS . ' HOUR), "%Y-%m-%dT%H:%i:%sZ")')->fetchColumn();
        return ['token' => $token, 'expires_at' => (string)$expires, 'profile' => $this->profileWithPermissions((int)$user['id'])];
    }

    private function profileWithPermissions(int $userId): array
    {
        $profile = (new CorporateInvitationService($this->pdo))->authenticatedProfile($userId);
        $profile['permissions'] = array_values(array_map(static fn(array $module): array => ['aplicacion' => $module['aplicacion'] ?? null, 'modulo' => $module['codigo'] ?? null, 'permissions' => $module['permissions'] ?? []], $profile['modules'] ?? []));
        return $profile;
    }

    private function rateLimit(string $email): void
    {
        $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')); $row = $this->rateRow($key);
        if ($row && $row['blocked_until'] && strtotime($row['blocked_until']) > time()) throw new HttpError(429, 'Demasiados intentos. Reintentá más tarde.', 'RATE_LIMITED');
    }
    private function registerFailure(string $email): void
    {
        $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')); $row = $this->rateRow($key); $attempts = $row && strtotime($row['window_started_at']) > time() - 900 ? (int)$row['attempts'] + 1 : 1; $blocked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
        $this->pdo->prepare('INSERT INTO auth_rate_limits (rate_key, attempts, window_started_at, blocked_until) VALUES (:k,:a,NOW(),:b) ON DUPLICATE KEY UPDATE attempts=VALUES(attempts), window_started_at=IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started_at), blocked_until=VALUES(blocked_until)')->execute(['k' => $key, 'a' => $attempts, 'b' => $blocked]);
    }
    private function clearFailures(string $email): void { $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')); $this->pdo->prepare('DELETE FROM auth_rate_limits WHERE rate_key = :k')->execute(['k' => $key]); }
    private function rateRow(string $key): ?array { $stmt = $this->pdo->prepare('SELECT * FROM auth_rate_limits WHERE rate_key = :k LIMIT 1'); $stmt->execute(['k' => $key]); $row = $stmt->fetch(); return is_array($row) ? $row : null; }
}
