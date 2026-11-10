<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';

require_admin();
$rows = db()->query('SELECT l.*, u.full_name FROM audit_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 300')->fetchAll();
$names = [
    'login' => 'Вход', 'logout' => 'Изход', 'answer_saved' => 'Запазен отговор', 'file_uploaded' => 'Качен файл',
    'file_deleted' => 'Изтрит файл', 'status_submitted' => 'Изпратено за преглед', 'status_reviewed' => 'Прегледано',
    'status_draft' => 'Върнато за допълване', 'user_created' => 'Нов профил', 'user_disabled' => 'Деактивиран профил',
    'user_enabled' => 'Активиран профил', 'user_role' => 'Смяна на роля', 'password_reset' => 'Нова парола (админ)',
    'password_changed' => 'Смяна на парола', 'export' => 'Експорт', 'installed' => 'Инсталация',
];
page_header('Журнал', 'log');
?>
<section class="card">
  <h1>Журнал на действията</h1>
  <p class="muted small">Последните 300 записа.</p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Време</th><th>Потребител</th><th>Действие</th><th>Детайли</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="small nowrap"><?= e(fmt_dt($r['created_at'])) ?></td>
        <td><?= e($r['full_name'] ?? '—') ?></td>
        <td><?= e($names[$r['action']] ?? $r['action']) ?></td>
        <td class="small"><?= e($r['details']) ?></td>
        <td class="small muted"><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php page_footer();
