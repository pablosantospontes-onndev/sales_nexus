<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    private static ?array $cachedUser = null;
    private static bool $presenceTouched = false;

    public static function attempt(string $cpf, string $password): bool
    {
        $repository = new UserRepository();
        $user = $repository->findByCpf($cpf);

        if ($user === null || ! (bool) $user['is_active']) {
            return false;
        }

        if (! password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = (int) $user['id'];
        self::$cachedUser = $user;
        self::$presenceTouched = false;

        $repository->touchLastLogin((int) $user['id']);
        $repository->touchPresence((int) $user['id'], session_id());
        self::$presenceTouched = true;

        return true;
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            self::touchPresenceIfNeeded(self::$cachedUser);

            return self::$cachedUser;
        }

        $userId = $_SESSION['auth_user_id'] ?? null;
        if ($userId === null) {
            return null;
        }

        $repository = new UserRepository();
        self::$cachedUser = $repository->findById((int) $userId);
        self::touchPresenceIfNeeded(self::$cachedUser);

        return self::$cachedUser;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user !== null ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function mustChangePassword(): bool
    {
        $user = self::user();

        return $user !== null && (int) ($user['must_change_password'] ?? 0) === 1;
    }

    public static function refreshUser(): ?array
    {
        self::$cachedUser = null;
        self::$presenceTouched = false;

        return self::user();
    }

    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();

        return $user !== null && in_array($user['role'], $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('ADMINISTRADOR');
    }

    public static function isBackofficeSupervisor(): bool
    {
        return self::hasRole('BACKOFFICE SUPERVISOR');
    }

    public static function requireLogin(): void
    {
        if (! self::check()) {
            flash('error', 'Sua sessão expirou. Entre novamente.');
            redirect('login');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();

        if (! self::hasRole(...$roles)) {
            flash('error', 'Você não tem permissão para acessar esta área.');
            redirect('dashboard');
        }
    }

    public static function logout(): void
    {
        $userId = isset($_SESSION['auth_user_id']) ? (int) $_SESSION['auth_user_id'] : 0;
        $sessionId = session_id();

        if ($userId > 0 && $sessionId !== '') {
            (new UserRepository())->clearPresence($userId, $sessionId);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        self::$cachedUser = null;
        self::$presenceTouched = false;
    }

    private static function touchPresenceIfNeeded(?array $user): void
    {
        if ($user === null || self::$presenceTouched) {
            return;
        }

        $userId = (int) ($user['id'] ?? 0);
        $sessionId = session_id();

        if ($userId <= 0 || $sessionId === '') {
            return;
        }

        (new UserRepository())->touchPresence($userId, $sessionId);
        self::$presenceTouched = true;
    }
}
