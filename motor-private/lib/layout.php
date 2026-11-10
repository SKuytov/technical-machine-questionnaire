<?php
declare(strict_types=1);

function page_header(string $title, string $active = ''): void
{
    $u = current_user();
    $appTitle = (string)mq_cfg('app.title', 'Технически въпросник');
    ?>
<!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($appTitle) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(MQ_VERSION) ?>">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<script src="assets/app.js?v=<?= e(MQ_VERSION) ?>" defer></script>
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="index.php">
      <svg width="28" height="28" viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="13" fill="none" stroke="currentColor" stroke-width="2.5"/><circle cx="16" cy="16" r="4.5" fill="currentColor"/><path d="M16 3v5M16 24v5M3 16h5M24 16h5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
      <span><?= e($appTitle) ?></span>
    </a>
    <?php if ($u): ?>
    <nav class="nav">
      <a href="index.php" class="<?= $active === 'q' ? 'on' : '' ?>">Въпросник</a>
      <a href="export.php" class="<?= $active === 'export' ? 'on' : '' ?>">Експорт</a>
      <?php if ($u['role'] === 'admin'): ?>
      <a href="admin.php" class="<?= $active === 'admin' ? 'on' : '' ?>">Потребители</a>
      <a href="log.php" class="<?= $active === 'log' ? 'on' : '' ?>">Журнал</a>
      <?php endif; ?>
      <a href="account.php" class="<?= $active === 'account' ? 'on' : '' ?>" title="Профил"><?= e($u['full_name']) ?></a>
      <form method="post" action="logout.php" class="inline"><?= csrf_field() ?><button class="linkbtn" type="submit">Изход</button></form>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main class="wrap">
<?php foreach (take_flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="foot wrap">
  <span>Инструмент за събиране на технически данни. Попълнените отговори не представляват инженерно одобрение на заместител.</span>
  <span>v<?= e(MQ_VERSION) ?></span>
</footer>
</body>
</html>
<?php
}
