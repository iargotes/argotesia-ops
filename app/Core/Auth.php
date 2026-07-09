<?php
declare(strict_types=1);

require_once __DIR__ . '/Security.php';

final class Auth
{
    public function __construct(private PDO $conn)
    {
        Security::startSession();
    }

    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->conn->prepare("
          SELECT id, worker_key, name, email, password_hash, role, status
          FROM users
          WHERE email = :email
            AND status = 'active'
          LIMIT 1
        ");
        $stmt->execute([':email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        return true;
    }

    public function user(): ?array
    {
        $id = (int)($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
          SELECT id, worker_key, name, email, role, status
          FROM users
          WHERE id = :id
            AND status = 'active'
          LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            $this->logout();
            return null;
        }

        return $user;
    }

    public function requireAuth(): array
    {
        $user = $this->user();
        if ($user) {
            return $user;
        }

        $returnTo = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login?return=' . urlencode($returnTo));
        exit;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}

