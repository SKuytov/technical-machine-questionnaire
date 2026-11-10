<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

require_login();
$qid = (int)($_GET['q'] ?? 0);
$questions = mq_questions();
if (!isset($questions[$qid])) {
    redirect('index.php');
}
$st = db()->prepare('SELECT h.*, u.full_name FROM answer_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.question_id = ? ORDER BY h.version DESC');
$st->execute([$qid]);
$rows = $st->fetchAll();
$conf = mq_confidence_labels();
page_header('История — въпрос ' . $qid, 'q');
?>
<section class="card">
  <p><a href="index.php#q<?= $qid ?>">← Обратно към въпросника</a></p>
  <h1>История на въпрос <?= $qid ?></h1>
  <p class="muted"><?= e($questions[$qid]['text']) ?></p>
  <?php if (!$rows): ?><p>Все още няма запазени версии.</p><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="hist">
      <p class="small"><strong>Версия <?= (int)$r['version'] ?></strong> · <?= e($r['full_name'] ?? '—') ?> · <?= e(fmt_dt($r['changed_at'])) ?><?php if ($r['confidence'] !== ''): ?> · <?= e($conf[$r['confidence']] ?? '') ?><?php endif; ?></p>
      <div class="answer-view"><?= trim((string)$r['answer_text']) !== '' ? nl2br(e($r['answer_text'])) : '<span class="muted">(празно)</span>' ?></div>
    </div>
  <?php endforeach; ?>
</section>
<?php page_footer();
