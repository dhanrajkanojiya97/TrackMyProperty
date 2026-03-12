<?php
declare(strict_types=1);

start_secure_session();

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (PHP_SAPI !== 'cli') {
        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $params['path'] !== '' ? $params['path'] : '/',
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    ini_set('session.use_strict_mode', '1');
    session_start();
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function sign_in_user(string $userId, string $userName, string $userEmail): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        start_secure_session();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_email'] = $userEmail;
}

function sign_out_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] !== '' ? $params['path'] : '/',
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = (string) ($_SESSION['_csrf'] ?? '');
    if ($sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function require_csrf_token(): void
{
    if (!verify_csrf_token((string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        echo 'Invalid form submission. Please refresh and try again.';
        exit;
    }
}

function parse_image_paths(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $paths = [];

    if ($raw[0] === '[') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $item = trim($item);
                if ($item !== '') {
                    $paths[] = $item;
                }
            }
        }
    }

    if (!$paths) {
        $parts = str_contains($raw, '|') ? explode('|', $raw) : [$raw];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $paths[] = $part;
            }
        }
    }

    return array_values(array_unique($paths));
}

function normalize_image_url(string $path): string
{
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    return ltrim(str_replace('\\', '/', $path), '/');
}

function image_first_url(string $raw, string $fallback): string
{
    $paths = parse_image_paths($raw);
    $first = $paths[0] ?? '';
    if ($first === '') {
        return $fallback;
    }

    return normalize_image_url($first);
}

function image_urls(string $raw, string $fallback): array
{
    $paths = parse_image_paths($raw);
    if (!$paths) {
        return [$fallback];
    }

    return array_map('normalize_image_url', $paths);
}
