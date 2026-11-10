<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

$me = require_admin();
$roles = mq_role_labels();
$error = '';
$form = ['username' => '', 'full_name' => '', 'position' => '', 'role' => 'technician'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $form = [
            'username'  => mb_strtolower(trim((string)($_POST['username'] ?? ''))),
            'full_name' => mb_substr(trim((string)($_POST['full_name'] ?? '')), 0, 120),
            'position'  => mb_substr(trim((string)($_POST['position'] ?? '')), 0, 120),
            'role'      => (string)($_POST['role'] ?? 'technician'),
        ];
        $pw = (string)($_POST['password'] ?? '');
        if (!isset($roles[$form['role']])) {
            $form['role'] = 'technician';
        }
        $error = validate_username($form['username']);
        if (!$error && $form['full_name'] === '') {
            $error = 'Въведете име.';
        }
        if (!$error) {
            $error = validate_password($pw);
        }
        if (!$error) {
            $st = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $st->execute([$form['username']]);
            if ((int)$st->fetchColumn() > 0) {
                $error = 'Това потребителско име вече съществува.';
            }
        }
        if (!$error) {
            db()->prepare('INSERT INTO users (username, full_name, position, password_hash, role, is_active, created_at) VALUES (?,?,?,?,?,1,?)')
                ->execute([$form['username'], $form['full_name'], $form['position'], password_hash($pw, PASSWORD_DEFAULT), $form['role'], now()]);
            audit('user_created', $form['username'] . ' (' . $form['role'] . ')');
            flash('success', 'Профилът „' . $form['username'] . '“ е създаден. Предайте паролата лично, не по имейл.');
            redirect('admin.php');
        }
    } else {
        $uid = (int)($_POST['user_id'] ?? 0);
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$uid]);
        $u = $st->fetch();
        if (!$u) {
            flash('error', 'Потребителят не е намерен.');
            redirect('admin.php');
        }
        if ($uid === (int)$me['id'] && in_array($action, ['toggle', 'role'], true)) {
            flash('error', 'Не можете да деактивирате или да смените ролята на собствения си профил.');
            redirect('admin.php');
        }
        if ($action === 'toggle') {
            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]);
            audit((int)$u['is_active'] ? 'user_disabled' : 'user_enabled', $u['username']);
            flash('success', 'Статусът на „' . $u['username'] . '“ е променен.');
        } elseif ($action === 'role') {
            $role = (string)($_POST['role'] ?? '');
            if (isset($roles[$role])) {
                db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
                audit('user_role', $u['username'] . ' → ' . $role);
                flash('success', 'Ролята е сменена.');
            }
        } elseif ($action === 'reset') {
            $pw = (string)($_POST['password'] ?? '');
            if ($m = validate_password($pw)) {
                flash('error', $m);
            } else {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
                audit('password_reset', $u['username']);
                flash('success', 'Паролата на „' . $u['username'] . '“ е сменена.');
            }
        }
        redirect('admin.php');
    }
}

$users = db()->query('SELECT * FROM users ORDER BY is_active DESC, role, full_name')->fetchAll();
page_header('Потребители', 'admin');
?>
<section class="card">
  <h1>Потребители</h1>
  <p class="muted">Няма публична регистрация. Създайте профил за главния техник и за техника на отдела. „Само преглед“ може да чете и сваля, но не и да редактира.</p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Име</th><th>Потребител</th><th>Роля</th><th>Статус</th><th>Последен вход</th><th>Действия</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
      <tr class="<?= (int)$u['is_active'] ? '' : 'off' ?>">
        <td><strong><?= e($u['full_name']) ?></strong><?php if ($u['position']): ?><br><span class="small muted"><?= e($u['position']) ?></span><?php endif; ?></td>
        <td><?= e($u['username']) ?></td>
        <td>
          <?php if ($self): ?><?= e($roles[$u['role']]) ?>
          <?php else: ?>
          <form method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <select name="role" data-autosubmit>
              <?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-sm" type="submit">OK</button></noscript>
          </form>
          <?php endif; ?>
        </td>
        <td><?= (int)$u['is_active'] ? 'Активен' : 'Деактивиран' ?></td>
        <td class="small"><?= e(fmt_dt($u['last_login_at'])) ?></td>
        <td><div class="actions">
          <?php if (!$self): ?>
          <form method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><?= (int)$u['is_active'] ? 'Деактивирай' : 'Активирай' ?></button>
          </form>
          <?php endif; ?>
          <details>
            <summary class="btn btn-ghost btn-sm">Нова парола</summary>
            <form method="post" class="stack mini">
              <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <input type="text" name="password" minlength="10" required placeholder="Нова парола" autocomplete="off">
              <button class="btn btn-secondary btn-sm" type="submit">Запази</button>
            </form>
          </details>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="card narrow">
  <h2>Нов профил</h2>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Име и фамилия <input type="text" name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="120"></label>
    <label>Длъжност <input type="text" name="position" value="<?= e($form['position']) ?>" maxlength="120" placeholder="напр. Главен техник"></label>
    <label>Потребителско име <input type="text" name="username" value="<?= e($form['username']) ?>" required pattern="[a-z0-9._\-]{3,50}" autocapitalize="none" placeholder="напр. ivan.petrov"></label>
    <label>Начална парола <input type="text" name="password" required minlength="10" autocomplete="off"></label>
    <label>Роля
      <select name="role">
        <?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>" <?= $form['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-primary" type="submit">Създай профил</button>
  </form>
</section>
<?php page_footer();
