<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return $cache = null;
    }
    $st = db()->prepare('SELECT id, username, full_name, position, role, is_active FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u || !(int)$u['is_active']) {
        unset($_SESSION['uid']);
        return $cache = null;
    }
    return $cache = $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Нямате права за тази страница.');
    }
    return $u;
}

function login_blocked(string $username): bool
{
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 0 AND attempted_at > ?');
    $st->execute([$username, $since]);
    if ((int)$st->fetchColumn() >= 5) {
        return true;
    }
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND attempted_at > ?');
    $st->execute([client_ip(), $since]);
    return (int)$st->fetchColumn() >= 20;
}

function attempt_login(string $username, string $password): string
{
    $username = mb_strtolower(trim($username));
    if ($username === '' || $password === '') {
        return 'Въведете потребителско име и парола.';
    }
    if (login_blocked($username)) {
        return 'Твърде много неуспешни опити. Опитайте отново след 15 минути.';
    }
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    $ok = $u && (int)$u['is_active'] && password_verify($password, $u['password_hash']);

    db()->prepare('INSERT INTO login_attempts (username, ip, success, attempted_at) VALUES (?,?,?,?)')
        ->execute([$username, client_ip(), $ok ? 1 : 0, now()]);

    if (!$ok) {
        return 'Грешно потребителско име или парола.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['csrf']);
    db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([now(), $u['id']]);
    audit('login', $u['username']);
    return '';
}

function logout(): void
{
    audit('logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function validate_password(string $pw): string
{
    if (mb_strlen($pw) < 10) {
        return 'Паролата трябва да е поне 10 символа.';
    }
    if (!preg_match('/[A-Za-zА-Яа-я]/u', $pw) || !preg_match('/\d/', $pw)) {
        return 'Паролата трябва да съдържа поне една буква и една цифра.';
    }
    return '';
}

function validate_username(string $un): string
{
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $un)) {
        return 'Потребителското име: 3–50 символа, само малки латински букви, цифри, точка, тире и долна черта.';
    }
    return '';
}
