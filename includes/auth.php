<?php
require_once __DIR__ . '/../config/app.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

function hasRole(string ...$roles): bool {
    $user = currentUser();
    if (!$user) return false;
    return in_array($user['role'], $roles);
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

function requireRole(string $redirect = '/', string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        header("Location: $redirect");
        exit;
    }
}

function login(array $user): void {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
}

function logout(): void {
    session_destroy();
}
