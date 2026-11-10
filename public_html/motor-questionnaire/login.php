<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

if (current_user()) {
    redirect('index.php');
}
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = (string)($_POST['username'] ?? '');
    $error = attempt_login($username, (string)($_POST['password'] ?? ''));
    if ($error === '') {
        redirect('index.php');
    }
}
page_header('Вход');
?>
<section class="card narrow">
  <h1>Вход</h1>
  <p class="muted">Технически въпросник за <?= e(mq_cfg('app.machine_title', '')) ?>. Достъпът е само с профил, създаден от администратора.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Потребителско име
      <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" required autofocus>
    </label>
    <label>Парола
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary" type="submit">Вход</button>
  </form>
</section>
<?php page_footer();
