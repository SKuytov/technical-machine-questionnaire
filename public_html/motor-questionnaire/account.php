<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

$me = require_login();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$me['id']]);
    $hash = (string)$st->fetchColumn();
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $rep = (string)($_POST['repeat'] ?? '');
    if (!password_verify($cur, $hash)) {
        $error = 'Текущата парола е грешна.';
    } elseif ($new !== $rep) {
        $error = 'Новите пароли не съвпадат.';
    } elseif (($error = validate_password($new)) === '') {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        session_regenerate_id(true);
        audit('password_changed');
        flash('success', 'Паролата е сменена.');
        redirect('account.php');
    }
}
$roles = mq_role_labels();
page_header('Профил', 'account');
?>
<section class="card narrow">
  <h1>Профил</h1>
  <dl class="kv">
    <dt>Име</dt><dd><?= e($me['full_name']) ?></dd>
    <dt>Длъжност</dt><dd><?= e($me['position'] ?: '—') ?></dd>
    <dt>Потребител</dt><dd><?= e($me['username']) ?></dd>
    <dt>Роля</dt><dd><?= e($roles[$me['role']] ?? $me['role']) ?></dd>
  </dl>
  <h2>Смяна на парола</h2>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Текуща парола <input type="password" name="current" autocomplete="current-password" required></label>
    <label>Нова парола <input type="password" name="new" autocomplete="new-password" minlength="10" required></label>
    <label>Повторете новата парола <input type="password" name="repeat" autocomplete="new-password" minlength="10" required></label>
    <p class="small muted">Поне 10 символа, с поне една буква и една цифра.</p>
    <button class="btn btn-primary" type="submit">Смени паролата</button>
  </form>
</section>
<?php page_footer();
