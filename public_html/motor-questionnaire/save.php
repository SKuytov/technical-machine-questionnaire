<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

$me = require_login();
verify_csrf();

$qid = (int)($_POST['question_id'] ?? 0);
$questions = mq_questions();
if (!isset($questions[$qid])) {
    flash('error', 'Непознат въпрос.');
    redirect('index.php');
}
if (!can_edit()) {
    flash('error', 'Досието е заключено за редакция.');
    redirect('index.php#q' . $qid);
}

$text = str_replace("\r\n", "\n", (string)($_POST['answer_text'] ?? ''));
$text = mb_substr($text, 0, 20000);
$conf = (string)($_POST['confidence'] ?? '');
if (!array_key_exists($conf, mq_confidence_labels())) {
    $conf = '';
}
$version = (int)($_POST['version'] ?? 0);

ensure_answer_rows();
$pdo = db();
$pdo->beginTransaction();
$st = $pdo->prepare('SELECT * FROM answers WHERE question_id = ? FOR UPDATE');
$st->execute([$qid]);
$cur = $st->fetch();

if ((int)$cur['version'] !== $version) {
    $pdo->rollBack();
    if (trim($text) !== trim((string)$cur['answer_text']) || $conf !== $cur['confidence']) {
        $_SESSION['conflict'][$qid] = ['text' => $text, 'confidence' => $conf];
        flash('warn', "Въпрос {$qid}: отговорът е променен от друг потребител. Прегледайте и запазете отново.");
    }
    redirect('index.php#q' . $qid);
}

if ($text === (string)$cur['answer_text'] && $conf === $cur['confidence']) {
    $pdo->rollBack();
    flash('info', "Въпрос {$qid}: няма промени.");
    redirect('index.php#q' . $qid);
}

$newVersion = $version + 1;
$pdo->prepare('UPDATE answers SET answer_text = ?, confidence = ?, version = ?, updated_by = ?, updated_at = ? WHERE question_id = ?')
    ->execute([$text, $conf, $newVersion, $me['id'], now(), $qid]);
$pdo->prepare('INSERT INTO answer_history (question_id, answer_text, confidence, version, changed_by, changed_at) VALUES (?,?,?,?,?,?)')
    ->execute([$qid, $text, $conf, $newVersion, $me['id'], now()]);
$pdo->commit();

audit('answer_saved', "Въпрос {$qid}, версия {$newVersion}");
flash('success', "Въпрос {$qid}: отговорът е запазен.");
redirect('index.php#q' . $qid);
