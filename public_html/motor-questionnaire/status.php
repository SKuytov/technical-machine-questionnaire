<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

$me = require_login();
verify_csrf();

$action = (string)($_POST['action'] ?? '');
$note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000);
$d = dossier();
$new = null;

if ($action === 'submit' && $d['status'] === 'draft' && $me['role'] !== 'viewer') {
    $new = 'submitted';
} elseif ($action === 'review' && $d['status'] === 'submitted' && $me['role'] === 'admin') {
    $new = 'reviewed';
} elseif ($action === 'reopen' && $d['status'] !== 'draft' && $me['role'] === 'admin') {
    $new = 'draft';
}

if (!$new) {
    flash('error', 'Действието не е позволено.');
    redirect('index.php');
}
$keepNote = $new === 'submitted' ? $d['review_note'] : ($note !== '' ? $note : null);
db()->prepare('UPDATE dossier SET status = ?, status_changed_by = ?, status_changed_at = ?, review_note = ? WHERE id = 1')
    ->execute([$new, $me['id'], now(), $keepNote]);
audit('status_' . $new, $note);
$labels = mq_status_labels();
flash('success', 'Статус: ' . $labels[$new] . '.');
redirect('index.php');
