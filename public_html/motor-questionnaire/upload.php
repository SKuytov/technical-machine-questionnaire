<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

$me = require_login();

// Ако файлът надвишава post_max_size, PHP изпразва $_POST — показваме ясно съобщение.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'Файловете са твърде големи за настройките на сървъра (post_max_size). Качете ги на части.');
    redirect('index.php');
}
verify_csrf();

$qid = (int)($_POST['question_id'] ?? -1);
if ($qid !== 0 && !isset(mq_questions()[$qid])) {
    flash('error', 'Непознат въпрос.');
    redirect('index.php');
}
$back = 'index.php#q' . $qid;
if (!can_edit()) {
    flash('error', 'Досието е заключено за редакция.');
    redirect($back);
}
if (!uploads_ready()) {
    flash('error', 'Папката за файлове (motor-private/uploads) липсва или няма права за запис.');
    redirect($back);
}

$desc = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 250);
$maxBytes = (int)mq_cfg('app.upload_max_mb', 20) * 1048576;
$allowed = allowed_uploads();
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

$f = $_FILES['files'] ?? null;
if (!$f || !is_array($f['name'])) {
    flash('error', 'Не е избран файл.');
    redirect($back);
}

$ok = 0; $errors = [];
$ins = db()->prepare('INSERT INTO attachments (question_id, original_name, stored_name, mime_type, size_bytes, description, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,?,?)');

foreach ($f['name'] as $i => $name) {
    $err = (int)$f['error'][$i];
    if ($err === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $orig = safe_filename((string)$name);
    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = $orig . ': ' . (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'твърде голям файл' : 'грешка при качване (' . $err . ')');
        continue;
    }
    $tmp = (string)$f['tmp_name'][$i];
    $size = (int)$f['size'][$i];
    if (!is_uploaded_file($tmp)) {
        $errors[] = $orig . ': невалиден файл';
        continue;
    }
    if ($size <= 0 || $size > $maxBytes) {
        $errors[] = $orig . ': размерът надвишава ' . mq_cfg('app.upload_max_mb', 20) . ' MB';
        continue;
    }
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        $errors[] = $orig . ': непозволен тип файл';
        continue;
    }
    $mime = $finfo ? (string)finfo_file($finfo, $tmp) : 'application/octet-stream';
    if ($finfo && !in_array($mime, $allowed[$ext], true)) {
        $errors[] = $orig . ': съдържанието не отговаря на разширението (' . $mime . ')';
        continue;
    }
    if (!$finfo) {
        $mime = $allowed[$ext][0];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $sub = date('Y');
    $dir = MQ_UPLOADS . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        $errors[] = $orig . ': неуспешно създаване на папка';
        continue;
    }
    if (!move_uploaded_file($tmp, $dir . '/' . $stored)) {
        $errors[] = $orig . ': неуспешен запис';
        continue;
    }
    @chmod($dir . '/' . $stored, 0640);
    $ins->execute([$qid, $orig, $sub . '/' . $stored, $mime, $size, $desc, $me['id'], now()]);
    audit('file_uploaded', ($qid ? "Въпрос {$qid}" : 'Общи') . ': ' . $orig);
    $ok++;
}

if ($ok) {
    flash('success', $ok === 1 ? 'Файлът е качен.' : "Качени файлове: {$ok}.");
}
foreach ($errors as $m) {
    flash('error', $m);
}
if (!$ok && !$errors) {
    flash('error', 'Не е избран файл.');
}
redirect($back);
