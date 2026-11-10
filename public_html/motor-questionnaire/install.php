<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

if (!mq_cfg('app.install_enabled', false)) {
    http_response_code(404);
    exit('Инсталацията е изключена.');
}

$checks = [
    ['PHP версия ≥ 7.4 (сега ' . PHP_VERSION . ')', PHP_VERSION_ID >= 70400, true],
    ['Разширение pdo_mysql', extension_loaded('pdo_mysql'), true],
    ['Разширение mbstring', extension_loaded('mbstring'), true],
    ['Разширение fileinfo (проверка на файлове)', extension_loaded('fileinfo'), false],
    ['Разширение zip (ZIP експорт)', class_exists('ZipArchive'), false],
    ['Папка motor-private/uploads с права за запис', uploads_ready(), true],
    ['HTTPS връзка', is_https(), false],
];
$blocking = false;
foreach ($checks as $c) {
    if ($c[2] && !$c[1]) {
        $blocking = true;
    }
}

$installed = false;
try {
    $installed = (int)db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->fetchColumn() > 0
        && (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (Throwable $t) {
    $installed = false;
}

$error = '';
$form = ['username' => '', 'full_name' => '', 'position' => ''];
if (!$installed && !$blocking && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'username'  => mb_strtolower(trim((string)($_POST['username'] ?? ''))),
        'full_name' => mb_substr(trim((string)($_POST['full_name'] ?? '')), 0, 120),
        'position'  => mb_substr(trim((string)($_POST['position'] ?? '')), 0, 120),
    ];
    $pw = (string)($_POST['password'] ?? '');
    $error = validate_username($form['username']);
    if (!$error && $form['full_name'] === '') {
        $error = 'Въведете име.';
    }
    if (!$error && $pw !== (string)($_POST['repeat'] ?? '')) {
        $error = 'Паролите не съвпадат.';
    }
    if (!$error) {
        $error = validate_password($pw);
    }
    if (!$error) {
        $sql = (string)file_get_contents(MQ_PRIVATE . '/schema.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', (string)$sql))) as $stmt) {
            db()->exec($stmt);
        }
        ensure_answer_rows();
        db()->prepare('INSERT INTO users (username, full_name, position, password_hash, role, is_active, created_at) VALUES (?,?,?,?,\'admin\',1,?)')
            ->execute([$form['username'], $form['full_name'], $form['position'], password_hash($pw, PASSWORD_DEFAULT), now()]);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        audit('installed', 'Администратор: ' . $form['username']);
        unset($_SESSION['uid']);
        flash('success', 'Инсталацията е завършена. Влезте с администраторския профил. ВАЖНО: в config.php сменете install_enabled на false.');
        redirect('login.php');
    }
}

page_header('Инсталация');
?>
<section class="card narrow">
  <h1>Първоначална инсталация</h1>
  <h2>Проверка на сървъра</h2>
  <ul class="checks">
    <?php foreach ($checks as $c): ?>
      <li class="<?= $c[1] ? 'ok' : ($c[2] ? 'bad' : 'warn') ?>"><?= $c[1] ? '✓' : ($c[2] ? '✗' : '!') ?> <?= e($c[0]) ?><?= !$c[1] && !$c[2] ? ' — препоръчително' : '' ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($installed): ?>
    <div class="alert alert-info">Приложението вече е инсталирано. В <code>motor-private/config.php</code> сменете <code>'install_enabled' =&gt; false</code>.</div>
    <p><a class="btn btn-primary" href="login.php">Към вход</a></p>
  <?php elseif ($blocking): ?>
    <div class="alert alert-error">Отстранете проблемите, отбелязани с ✗, и презаредете страницата.</div>
  <?php else: ?>
    <h2>Администраторски профил</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <label>Име и фамилия <input type="text" name="full_name" value="<?= e($form['full_name']) ?>" required></label>
      <label>Длъжност <input type="text" name="position" value="<?= e($form['position']) ?>" placeholder="напр. Организатор ремонт и поддръжка"></label>
      <label>Потребителско име <input type="text" name="username" value="<?= e($form['username']) ?>" required pattern="[a-z0-9._\-]{3,50}" autocapitalize="none"></label>
      <label>Парола <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label>Повторете паролата <input type="password" name="repeat" required minlength="10" autocomplete="new-password"></label>
      <button class="btn btn-primary" type="submit">Създай базата и администратора</button>
    </form>
  <?php endif; ?>
</section>
<?php page_footer();
