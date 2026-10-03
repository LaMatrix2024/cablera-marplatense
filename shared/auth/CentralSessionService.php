<?php
declare(strict_types=1);
require_once __DIR__ . '/HttpError.php';

final class CentralSessionService
{
    private const COOKIE = 'lcm_session';
    private const LOCAL_SESSION = 'lcm_local_auth';
    private const TTL = 28800;
    private const PROFILE_TTL = 300;
    private const OFFLINE_TOLERANCE = 900;
    private ?array $cachedRateRow = null;
    private ?string $cachedRateKey = null;

    public function __construct(private PDO $pdo) { $this->startLocalSession(); }

    /** Lectura ligera usada por /me antes de abrir una conexión remota. */
    public static function localSnapshot(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_name('lcm_local');
            session_set_cookie_params(['lifetime' => self::TTL, 'path' => '/', 'secure' => self::secureStatic(), 'httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
        $auth = $_SESSION[self::LOCAL_SESSION] ?? null;
        if (!is_array($auth) || !is_array($auth['profile'] ?? null) || (int)($auth['expires_at'] ?? 0) <= time()) return null;
        return ['profile' => $auth['profile'], 'fresh' => (int)($auth['profile_refreshed_at'] ?? 0) + self::PROFILE_TTL > time(), 'offline' => (int)($auth['offline_until'] ?? 0) > time()];
    }

    public static function localCsrfValid(string $provided): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) self::localSnapshot();
        $auth = $_SESSION[self::LOCAL_SESSION] ?? null;
        return is_array($auth) && is_string($auth['csrf_hash'] ?? null) && hash_equals($auth['csrf_hash'], hash('sha256', $provided));
    }

    public static function logoutLocal(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) self::localSnapshot();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        setcookie('lcm_local', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => self::secureStatic()]);
        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => self::secureStatic()]);
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') throw new HttpError(401, 'Correo o contraseña incorrectos.', 'invalid_credentials');
        $this->rateLimit($email);
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        $valid = is_array($user) && is_string($user['password_hash'] ?? null) && password_verify($password, $user['password_hash']);
        if (!$valid) { $this->registerFailure($email); throw new HttpError(401, 'Correo o contraseña incorrectos.', 'invalid_credentials'); }
        if (($user['estado'] ?? '') !== 'ACTIVO') throw new HttpError(403, 'La cuenta no está activa.', 'inactive_user');
        $this->clearFailures($email);
        $token = bin2hex(random_bytes(32)); $csrf = bin2hex(random_bytes(32)); $version = (int)($user['sesion_version'] ?? 1);
        $insert = $this->pdo->prepare('INSERT INTO auth_sesiones (usuario_id, token_hash, csrf_hash, sesion_version, expires_at, ip_address, user_agent) VALUES (:usuario_id, :token_hash, :csrf_hash, :version, DATE_ADD(NOW(), INTERVAL 8 HOUR), :ip, :ua)');
        $insert->execute(['usuario_id' => (int)$user['id'], 'token_hash' => hash('sha256', $token), 'csrf_hash' => hash('sha256', $csrf), 'version' => $version, 'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, 'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null]);
        $this->setCookie($token);
        return ['csrf_token' => $csrf, 'user_id' => (int)$user['id'], 'session_version' => $version];
    }

    public function localProfile(bool $required = true): ?array
    {
        $auth = $_SESSION[self::LOCAL_SESSION] ?? null;
        if (!is_array($auth) || !is_array($auth['profile'] ?? null)) { if ($required) throw new HttpError(401, 'Sesión no iniciada.', 'unauthenticated'); return null; }
        if ((int)($auth['expires_at'] ?? 0) <= time()) { $this->clearLocalSession(); if ($required) throw new HttpError(401, 'Sesión vencida.', 'session_expired'); return null; }
        return $auth['profile'];
    }
    public function localIsFresh(): bool { $auth = $_SESSION[self::LOCAL_SESSION] ?? null; return is_array($auth) && (int)($auth['profile_refreshed_at'] ?? 0) + self::PROFILE_TTL > time(); }
    public function localCanUseOffline(): bool { $auth = $_SESSION[self::LOCAL_SESSION] ?? null; return is_array($auth) && (int)($auth['offline_until'] ?? 0) > time(); }
    public function saveLocalProfile(array $profile, string $csrf, int $sessionVersion): void
    {
        session_regenerate_id(true);
        $_SESSION[self::LOCAL_SESSION] = ['profile' => $profile, 'profile_refreshed_at' => time(), 'offline_until' => time() + self::OFFLINE_TOLERANCE, 'expires_at' => time() + self::TTL, 'session_version' => $sessionVersion, 'csrf_token' => $csrf, 'csrf_hash' => hash('sha256', $csrf)];
    }
    public function refreshRemote(): array
    {
        $row = $this->remoteCurrent(true);
        require_once __DIR__ . '/CorporateInvitationService.php';
        $profile = (new CorporateInvitationService($this->pdo))->authenticatedProfile((int)$row['user_id']);
        $auth = $_SESSION[self::LOCAL_SESSION] ?? [];
        $_SESSION[self::LOCAL_SESSION] = ['profile' => $profile, 'profile_refreshed_at' => time(), 'offline_until' => time() + self::OFFLINE_TOLERANCE, 'expires_at' => time() + self::TTL, 'session_version' => (int)$row['session_version'], 'csrf_hash' => (string)($auth['csrf_hash'] ?? '')];
        return $profile;
    }
    public function current(bool $required = true): ?array { return $this->remoteCurrent($required); }
    public function csrf(string $provided): void
    {
        $hashProvided = hash('sha256', $provided); $local = $_SESSION[self::LOCAL_SESSION] ?? null;
        if (is_array($local) && is_string($local['csrf_hash'] ?? null) && hash_equals($local['csrf_hash'], $hashProvided)) return;
        $token = $_COOKIE[self::COOKIE] ?? ''; $expected = $this->pdo->prepare('SELECT csrf_hash FROM auth_sesiones WHERE token_hash = :token AND revoked_at IS NULL LIMIT 1'); $expected->execute(['token' => hash('sha256', (string)$token)]); $hash = $expected->fetchColumn();
        if (!is_string($hash) || !hash_equals($hash, $hashProvided)) throw new HttpError(403, 'Solicitud no válida.', 'csrf_invalid');
    }
    public function logout(): void
    {
        $token = $_COOKIE[self::COOKIE] ?? ''; $this->clearLocalSession();
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) { try { $this->pdo->prepare('UPDATE auth_sesiones SET revoked_at = NOW() WHERE token_hash = :token')->execute(['token' => hash('sha256', $token)]); } catch (Throwable) {} }
        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $this->secure()]);
    }
    private function remoteCurrent(bool $required): ?array
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) { if ($required) throw new HttpError(401, 'Sesión no iniciada.', 'unauthenticated'); return null; }
        $stmt = $this->pdo->prepare('SELECT s.*, s.sesion_version AS session_version, u.id AS user_id, u.email, u.nombre, u.apellido, u.estado, u.es_superadmin, u.sesion_version AS user_session_version FROM auth_sesiones s INNER JOIN usuarios u ON u.id = s.usuario_id WHERE s.token_hash = :token AND s.revoked_at IS NULL AND s.expires_at > NOW() LIMIT 1'); $stmt->execute(['token' => hash('sha256', $token)]); $row = $stmt->fetch();
        if (!is_array($row) || (int)$row['session_version'] !== (int)$row['user_session_version'] || ($row['estado'] ?? '') !== 'ACTIVO') { if ($required) throw new HttpError(401, 'Sesión vencida o revocada.', 'session_invalid'); return null; }
        $this->pdo->prepare('UPDATE auth_sesiones SET last_used_at = NOW() WHERE id = :id')->execute(['id' => $row['id']]); return $row;
    }
    private function startLocalSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1'); session_name('lcm_local'); session_set_cookie_params(['lifetime' => self::TTL, 'path' => '/', 'secure' => $this->secure(), 'httponly' => true, 'samesite' => 'Lax']); session_start();
    }
    private function clearLocalSession(): void { $_SESSION = []; if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $this->secure()]); }
    private function setCookie(string $token): void { setcookie(self::COOKIE, $token, ['expires' => time() + self::TTL, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $this->secure()]); }
    private function secure(): bool { return ($_SERVER['HTTPS'] ?? '') === 'on' || strcasecmp((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0; }
    private static function secureStatic(): bool { return ($_SERVER['HTTPS'] ?? '') === 'on' || strcasecmp((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0; }
    private function rateLimit(string $email): void { $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')); $row = $this->rateRow($key); if ($row && $row['blocked_until'] && strtotime($row['blocked_until']) > time()) throw new HttpError(429, 'Demasiados intentos. Reintentá más tarde.', 'rate_limited'); }
    private function registerFailure(string $email): void { $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')); $row = $this->rateRow($key); $attempts = $row && strtotime($row['window_started_at']) > time() - 900 ? (int)$row['attempts'] + 1 : 1; $blocked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null; $this->pdo->prepare('INSERT INTO auth_rate_limits (rate_key, attempts, window_started_at, blocked_until) VALUES (:k,:a,NOW(),:b) ON DUPLICATE KEY UPDATE attempts=VALUES(attempts), window_started_at=IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started_at), blocked_until=VALUES(blocked_until)')->execute(['k' => $key, 'a' => $attempts, 'b' => $blocked]); }
    private function clearFailures(string $email): void { $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? '')); if ($this->cachedRateKey === $key && $this->cachedRateRow === null) return; $this->pdo->prepare('DELETE FROM auth_rate_limits WHERE rate_key = :k')->execute(['k' => $key]); }
    private function rateRow(string $key): ?array { if ($this->cachedRateKey === $key) return $this->cachedRateRow; $s = $this->pdo->prepare('SELECT * FROM auth_rate_limits WHERE rate_key = :k LIMIT 1'); $s->execute(['k' => $key]); $row = $s->fetch(); $this->cachedRateKey = $key; $this->cachedRateRow = is_array($row) ? $row : null; return $this->cachedRateRow; }
}
