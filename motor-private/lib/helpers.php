<?php
declare(strict_types=1);

function mq_cfg(string $key, $default = null)
{
    $node = $GLOBALS['MQ_CONFIG'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = mq_cfg('db');
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset'] ?? 'utf8mb4');
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    } catch (PDOException $ex) {
        http_response_code(500);
        error_log('MQ DB error: ' . $ex->getMessage());
        exit('Няма връзка с базата данни. Проверете motor-private/config.php.');
    }
    return $pdo;
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function fmt_dt(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $t = strtotime($dt);
    return $t ? date('d.m.Y H:i', $t) : '—';
}

function fmt_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' MB';
    }
    return number_format(max(1, (int)round($bytes / 1024)), 0, ',', ' ') . ' KB';
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function audit(string $action, string $details = ''): void
{
    try {
        $st = db()->prepare('INSERT INTO audit_log (user_id, action, details, ip, created_at) VALUES (?,?,?,?,?)');
        $st->execute([current_user()['id'] ?? null, $action, mb_substr($details, 0, 500), client_ip(), now()]);
    } catch (Throwable $t) {
        error_log('MQ audit failed: ' . $t->getMessage());
    }
}

/* ---------- Сигурност ---------- */

function mq_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function mq_start_session(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name((string)mq_cfg('app.session_name', 'MQSESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    $idle = (int)mq_cfg('app.session_idle_h', 8) * 3600;
    if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string)($_POST['csrf'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Невалидна или изтекла форма. Презаредете страницата и опитайте отново.');
    }
}

/* ---------- Досие / отговори ---------- */

function dossier(): array
{
    $row = db()->query('SELECT * FROM dossier WHERE id = 1')->fetch();
    if (!$row) {
        db()->exec("INSERT IGNORE INTO dossier (id, status) VALUES (1, 'draft')");
        $row = ['id' => 1, 'status' => 'draft', 'status_changed_by' => null, 'status_changed_at' => null, 'review_note' => null];
    }
    return $row;
}

function ensure_answer_rows(): void
{
    $st = db()->prepare('INSERT IGNORE INTO answers (question_id, answer_text, confidence, version) VALUES (?, NULL, \'\', 0)');
    foreach (array_keys(mq_questions()) as $qid) {
        $st->execute([$qid]);
    }
}

function load_answers(): array
{
    $rows = db()->query('SELECT a.*, u.full_name AS updated_by_name FROM answers a LEFT JOIN users u ON u.id = a.updated_by')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(int)$r['question_id']] = $r;
    }
    return $out;
}

function load_attachments(): array
{
    $rows = db()->query('SELECT t.*, u.full_name AS uploaded_by_name FROM attachments t LEFT JOIN users u ON u.id = t.uploaded_by ORDER BY t.uploaded_at, t.id')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(int)$r['question_id']][] = $r;
    }
    return $out;
}

function answer_is_filled(?array $a): bool
{
    return $a && ($a['confidence'] !== '' || trim((string)$a['answer_text']) !== '');
}

function can_edit(): bool
{
    $u = current_user();
    if (!$u || $u['role'] === 'viewer') {
        return false;
    }
    return dossier()['status'] === 'draft';
}

/* ---------- Файлове ---------- */

function allowed_uploads(): array
{
    // разширение => допустими MIME типове (проверка чрез finfo)
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'heif' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'mp4'  => ['video/mp4'],
        'mov'  => ['video/quicktime'],
    ];
}

function inline_mimes(): array
{
    return ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
}

function safe_filename(string $name): string
{
    $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $name) ?? 'file';
    $name = trim($name, " .");
    if ($name === '') {
        $name = 'file';
    }
    return mb_substr($name, 0, 150);
}

function uploads_ready(): bool
{
    return is_dir(MQ_UPLOADS) && is_writable(MQ_UPLOADS);
}
