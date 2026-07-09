<?php
declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    public static function csrfCheck(?string $token): bool
    {
        self::startSession();
        $stored = $_SESSION['_csrf'] ?? '';
        return is_string($stored) && $stored !== '' && is_string($token) && hash_equals($stored, $token);
    }

    public static function requireCsrf(): void
    {
        if (!self::csrfCheck($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Sesion invalida.');
        }
    }
}

