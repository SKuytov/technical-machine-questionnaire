<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

$me = require_login();
verify_csrf();

$back = 'index.php#' . preg_replace('/[^a-z0-9]/', '', (string)($_POST['back'] ?? ''));
if (!can_edit()) {
    flash('error', 'Досието е заключено за редакция.');
    redirect($back);
}
$st = db()->prepare('SELECT * FROM attachments WHERE id = ?');
$st->execute([(int)($_POST['id'] ?? 0)]);
$f = $st->fetch();
if (!$f) {
    flash('error', 'Файлът не е намерен.');
    redirect($back);
}
if ($me['role'] !== 'admin' && (int)$f['uploaded_by'] !== (int)$me['id']) {
    flash('error', 'Можете да изтривате само файлове, качени от Вас.');
    redirect($back);
}
$path = MQ_UPLOADS . '/' . $f['stored_name'];
if (is_file($path)) {
    @unlink($path);
}
db()->prepare('DELETE FROM attachments WHERE id = ?')->execute([$f['id']]);
audit('file_deleted', $f['original_name']);
flash('success', 'Файлът е изтрит.');
redirect($back);
