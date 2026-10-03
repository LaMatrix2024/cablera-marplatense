<?php
declare(strict_types=1);

final class LocalAuthSession
{
    private const KEY = 'lcm_local_auth';
    private const TTL = 28800;
    private const PROFILE_TTL = 300;
    private const OFFLINE_TOLERANCE = 900;

    public function __construct() { self::start(); }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('lcm_local');
        session_set_cookie_params(['lifetime' => self::TTL, 'path' => '/', 'secure' => self::secure(), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }

    public function snapshot(): ?array
    {
        $auth = $_SESSION[self::KEY] ?? null;
        if (!is_array($auth) || !is_array($auth['profile'] ?? null) || (int)($auth['expires_at'] ?? 0) <= time()) return null;
        return ['profile' => $auth['profile'], 'token' => (string)($auth['central_token'] ?? ''), 'csrf_hash' => (string)($auth['csrf_hash'] ?? ''), 'csrf_token' => (string)($auth['csrf_token'] ?? ''), 'fresh' => (int)($auth['profile_refreshed_at'] ?? 0) + self::PROFILE_TTL > time(), 'offline' => (int)($auth['offline_until'] ?? 0) > time()];
    }

    public function establish(array $profile, string $centralToken, string $centralExpiresAt): string
    {
        session_regenerate_id(true);
        $csrf = bin2hex(random_bytes(32));
        $_SESSION[self::KEY] = ['profile' => $profile, 'central_token' => $centralToken, 'central_expires_at' => $centralExpiresAt, 'profile_refreshed_at' => time(), 'offline_until' => time() + self::OFFLINE_TOLERANCE, 'expires_at' => time() + self::TTL, 'csrf_token' => $csrf, 'csrf_hash' => hash('sha256', $csrf)];
        return $csrf;
    }

    /** Devuelve el token CSRF de la sesión local, rehidratándolo sólo para sesiones antiguas. */
    public function csrfToken(): string
    {
        $auth = $_SESSION[self::KEY] ?? null;
        if (!is_array($auth) || !is_array($auth['profile'] ?? null)) return '';
        $token = (string)($auth['csrf_token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            $auth['csrf_token'] = $token;
            $auth['csrf_hash'] = hash('sha256', $token);
            $_SESSION[self::KEY] = $auth;
        }
        return $token;
    }

    public function refresh(array $profile, string $centralExpiresAt): void
    {
        $auth = $_SESSION[self::KEY] ?? null;
        if (!is_array($auth)) return;
        $auth['profile'] = $profile;
        $auth['central_expires_at'] = $centralExpiresAt;
        $auth['profile_refreshed_at'] = time();
        $auth['offline_until'] = time() + self::OFFLINE_TOLERANCE;
        $auth['expires_at'] = time() + self::TTL;
        $_SESSION[self::KEY] = $auth;
    }

    public function csrfValid(string $provided): bool
    {
        $snap = $this->snapshot();
        return is_array($snap) && $snap['csrf_hash'] !== '' && hash_equals($snap['csrf_hash'], hash('sha256', $provided));
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        setcookie('lcm_local', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => self::secure(), 'httponly' => true, 'samesite' => 'Lax']);
    }

    private static function secure(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') === 'on' || strcasecmp((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0;
    }
}
