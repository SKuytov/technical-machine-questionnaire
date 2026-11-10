<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

$me = require_login();
ensure_answer_rows();

$questions = mq_questions();
$sections  = mq_sections();
$answers   = load_answers();
$files     = load_attachments();
$dossier   = dossier();
$editable  = can_edit();
$conflicts = $_SESSION['conflict'] ?? [];
unset($_SESSION['conflict']);

$filled = 0; $prioFilled = 0; $prioTotal = 0;
foreach ($questions as $qid => $q) {
    $ok = answer_is_filled($answers[$qid] ?? null);
    $filled += $ok ? 1 : 0;
    if ($q['priority']) { $prioTotal++; $prioFilled += $ok ? 1 : 0; }
}
$total = count($questions);
$pct = $total ? (int)round($filled * 100 / $total) : 0;
$statusLabels = mq_status_labels();
$confLabels = mq_confidence_labels();
$maxMb = (int)mq_cfg('app.upload_max_mb', 20);

$changedBy = '';
if ($dossier['status_changed_by']) {
    $st = db()->prepare('SELECT full_name FROM users WHERE id = ?');
    $st->execute([$dossier['status_changed_by']]);
    $changedBy = (string)$st->fetchColumn();
}

function render_files(array $list, int $qid, array $me, bool $editable): void
{
    if (!$list) { return; }
    echo '<ul class="files">';
    foreach ($list as $f) {
        $isImg = strpos($f['mime_type'], 'image/') === 0 && in_array($f['mime_type'], inline_mimes(), true);
        echo '<li class="file">';
        if ($isImg) {
            echo '<a href="download.php?id=' . (int)$f['id'] . '&amp;inline=1" target="_blank" rel="noopener" class="thumb"><img loading="lazy" src="download.php?id=' . (int)$f['id'] . '&amp;inline=1" alt=""></a>';
        } else {
            $ext = strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION) ?: 'FILE');
            echo '<span class="thumb ext">' . e(substr($ext, 0, 4)) . '</span>';
        }
        echo '<div class="file-meta"><a href="download.php?id=' . (int)$f['id'] . '">' . e($f['original_name']) . '</a>';
        if ($f['description'] !== '') { echo '<span class="desc">' . e($f['description']) . '</span>'; }
        echo '<span class="muted">' . e(fmt_size((int)$f['size_bytes'])) . ' · ' . e($f['uploaded_by_name'] ?? '—') . ' · ' . e(fmt_dt($f['uploaded_at'])) . '</span></div>';
        if ($editable && ($me['role'] === 'admin' || (int)$f['uploaded_by'] === (int)$me['id'])) {
            echo '<form method="post" action="attachment_delete.php" class="inline" data-confirm="Да се изтрие ли файлът „' . e($f['original_name']) . '“?">' . csrf_field()
                . '<input type="hidden" name="id" value="' . (int)$f['id'] . '"><input type="hidden" name="back" value="q' . $qid . '">'
                . '<button type="submit" class="btn btn-ghost btn-sm" title="Изтрий">Изтрий</button></form>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

function render_upload(int $qid, int $maxMb): void
{
    ?>
    <form method="post" action="upload.php" enctype="multipart/form-data" class="upload">
      <?= csrf_field() ?>
      <input type="hidden" name="question_id" value="<?= $qid ?>">
      <label class="file-pick">
        <input type="file" name="files[]" multiple accept="image/*,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.mp4,.mov">
        <span>Добави снимки / документи</span>
      </label>
      <input type="text" name="description" maxlength="250" placeholder="Кратко описание (по избор)">
      <button type="submit" class="btn btn-secondary btn-sm">Качи</button>
      <span class="muted small">до <?= $maxMb ?> MB на файл</span>
    </form>
    <?php
}

page_header('Въпросник', 'q');
?>
<section class="card hero">
  <div class="hero-main">
    <p class="eyebrow">Машинно досие</p>
    <h1><?= e(mq_cfg('app.machine_title', '')) ?></h1>
    <p class="muted">Сериен № <strong><?= e(mq_cfg('app.machine_serial', '')) ?></strong> · Табелка: 60 Hz · 600/75 min⁻¹ · i = 7,97 · BE2 14 Nm · BME1.5 · M1</p>
    <p class="status-line">
      <span class="badge badge-<?= e($dossier['status']) ?>"><?= e($statusLabels[$dossier['status']]) ?></span>
      <?php if ($dossier['status_changed_at']): ?>
        <span class="muted small"><?= e($changedBy ?: '—') ?> · <?= e(fmt_dt($dossier['status_changed_at'])) ?></span>
      <?php endif; ?>
    </p>
    <?php if (!empty($dossier['review_note'])): ?>
      <div class="note"><strong>Бележка от прегледа:</strong> <?= nl2br(e($dossier['review_note'])) ?></div>
    <?php endif; ?>
  </div>
  <div class="hero-side">
    <div class="progress-num"><?= $filled ?>/<?= $total ?></div>
    <progress class="progress" value="<?= $filled ?>" max="<?= $total ?>"><?= $pct ?>%</progress>
    <p class="small muted">Приоритетни (1–8): <strong><?= $prioFilled ?>/<?= $prioTotal ?></strong></p>
    <div class="workflow">
      <?php if ($dossier['status'] === 'draft' && $me['role'] !== 'viewer'): ?>
        <form method="post" action="status.php" data-confirm="След изпращане отговорите се заключват до преглед. Продължаване?">
          <?= csrf_field() ?><input type="hidden" name="action" value="submit">
          <button class="btn btn-primary" type="submit">Изпрати за преглед</button>
        </form>
      <?php endif; ?>
      <?php if ($me['role'] === 'admin' && $dossier['status'] === 'submitted'): ?>
        <form method="post" action="status.php" class="stack">
          <?= csrf_field() ?><input type="hidden" name="action" value="review">
          <textarea name="note" rows="2" placeholder="Бележка (по избор)"></textarea>
          <button class="btn btn-primary" type="submit">Отбележи като прегледано</button>
        </form>
      <?php endif; ?>
      <?php if ($me['role'] === 'admin' && $dossier['status'] !== 'draft'): ?>
        <form method="post" action="status.php" class="stack">
          <?= csrf_field() ?><input type="hidden" name="action" value="reopen">
          <textarea name="note" rows="2" placeholder="Какво трябва да се допълни? (по избор)"></textarea>
          <button class="btn btn-secondary" type="submit">Върни за допълване</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if (!$editable): ?>
  <div class="alert alert-info">
    <?= $me['role'] === 'viewer' ? 'Профилът Ви е само за преглед.' : 'Досието е заключено (' . e($statusLabels[$dossier['status']]) . '). За промени администраторът трябва да го върне за допълване.' ?>
  </div>
<?php endif; ?>

<div class="safety">
  <strong>Безопасност:</strong> не освобождавайте спирачката, не разкачвайте задвижването и не правете тест със спрял ротор само за да отговорите. Отваряне на клемна кутия и измервания — само от упълномощени лица, при изключено и обезопасено захранване и обезопасен товар.
</div>

<nav class="chips" aria-label="Раздели">
  <?php foreach ($sections as $key => $label): ?><a href="#s-<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
  <a href="#s-general">Общи файлове</a>
</nav>

<?php foreach ($sections as $skey => $slabel): ?>
<section id="s-<?= e($skey) ?>" class="section">
  <h2><?= e($slabel) ?></h2>
  <?php foreach ($questions as $qid => $q): if ($q['section'] !== $skey) { continue; }
      $a = $answers[$qid] ?? ['answer_text' => '', 'confidence' => '', 'version' => 0, 'updated_by_name' => null, 'updated_at' => null];
      $done = answer_is_filled($a);
  ?>
  <article id="q<?= $qid ?>" class="card q <?= $done ? 'done' : '' ?> <?= $q['priority'] ? 'prio' : '' ?>">
    <header class="q-head">
      <span class="q-num"><?= $qid ?></span>
      <div>
        <h3><?= e($q['text']) ?></h3>
        <?php if ($q['hint'] !== ''): ?><p class="hint"><?= e($q['hint']) ?></p><?php endif; ?>
      </div>
      <div class="q-tags">
        <?php if ($q['priority']): ?><span class="tag tag-prio">Приоритет</span><?php endif; ?>
        <span class="tag <?= $done ? 'tag-done' : 'tag-open' ?>"><?= $done ? 'Отговорено' : 'Без отговор' ?></span>
      </div>
    </header>

    <?php if (isset($conflicts[$qid])): ?>
      <div class="alert alert-warn">
        <strong>Конфликт:</strong> докато сте редактирали, друг потребител е запазил отговор. По-долу е актуалният запазен текст. Вашият незапазен текст:
        <pre class="conflict"><?= e($conflicts[$qid]['text']) ?></pre>
        Обединете двата текста и запазете отново.
      </div>
    <?php endif; ?>

    <?php if ($editable): ?>
    <form method="post" action="save.php" class="answer-form" data-dirty-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="question_id" value="<?= $qid ?>">
      <input type="hidden" name="version" value="<?= (int)$a['version'] ?>">
      <label class="sr" for="t<?= $qid ?>">Отговор</label>
      <textarea id="t<?= $qid ?>" name="answer_text" rows="4" placeholder="Вашият отговор…"><?= e($a['answer_text']) ?></textarea>
      <div class="row">
        <label class="inline-label">Достоверност
          <select name="confidence">
            <?php foreach ($confLabels as $k => $lbl): ?>
              <option value="<?= e($k) ?>" <?= $a['confidence'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn btn-primary">Запази</button>
        <a class="small" href="history.php?q=<?= $qid ?>">История</a>
      </div>
    </form>
    <?php else: ?>
      <div class="answer-view">
        <?= trim((string)$a['answer_text']) !== '' ? nl2br(e($a['answer_text'])) : '<span class="muted">Няма отговор.</span>' ?>
        <?php if ($a['confidence'] !== ''): ?><p class="small"><strong>Достоверност:</strong> <?= e($confLabels[$a['confidence']] ?? '') ?></p><?php endif; ?>
        <a class="small" href="history.php?q=<?= $qid ?>">История</a>
      </div>
    <?php endif; ?>

    <p class="meta muted small">
      <?php if ($a['updated_at']): ?>Последна промяна: <?= e($a['updated_by_name'] ?? '—') ?> · <?= e(fmt_dt($a['updated_at'])) ?><?php else: ?>Все още няма запис.<?php endif; ?>
    </p>

    <?php render_files($files[$qid] ?? [], $qid, $me, $editable); ?>
    <?php if ($editable) { render_upload($qid, $maxMb); } ?>
  </article>
  <?php endforeach; ?>
</section>
<?php endforeach; ?>

<section id="s-general" class="section">
  <h2>Общи файлове</h2>
  <article id="q0" class="card q">
    <p class="hint">Снимки на целия механизъм, табелка на машината, етикети на мотора/спирачката/управлението, сканирани схеми и всичко, което не е към конкретен въпрос.</p>
    <?php render_files($files[0] ?? [], 0, $me, $editable); ?>
    <?php if ($editable) { render_upload(0, $maxMb); } ?>
  </article>
</section>
<?php page_footer();
