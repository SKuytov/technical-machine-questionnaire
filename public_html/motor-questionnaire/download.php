<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM attachments WHERE id = ?');
$st->execute([$id]);
$f = $st->fetch();
if (!$f) {
    http_response_code(404);
    exit('Файлът не е намерен.');
}
$path = realpath(MQ_UPLOADS . '/' . $f['stored_name']);
$root = realpath(MQ_UPLOADS);
if (!$path || !$root || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('Файлът липсва на сървъра.');
}

$inline = !empty($_GET['inline']) && in_array($f['mime_type'], inline_mimes(), true);
$name = $f['original_name'];
$ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'file';

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: ' . ($inline ? $f['mime_type'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Cache-Control: private, max-age=3600');
if ($f['mime_type'] !== 'application/pdf') {
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
readfile($path);
