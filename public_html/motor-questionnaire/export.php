<?php
declare(strict_types=1);
require __DIR__ . '/_paths.php';
require_once MQ_PRIVATE_DIR . '/lib/export.php';

require_login();
$format = (string)($_GET['format'] ?? '');

function strip_stored(array $data): array
{
    $clean = function (array $files): array {
        return array_map(function ($f) { unset($f['stored']); return $f; }, $files);
    };
    $data['general_files'] = $clean($data['general_files']);
    foreach ($data['items'] as &$it) {
        $it['files'] = $clean($it['files']);
    }
    return $data;
}
$stamp = date('Y-m-d_Hi');
$base = 'vaprosnik_R37_DRM100L12_' . $stamp;

if ($format === 'html') {
    $data = export_data();
    audit('export', 'HTML');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'");
    header('Content-Type: text/html; charset=utf-8');
    echo export_html($data, false);
    exit;
}

if ($format === 'csv' || $format === 'json') {
    $data = export_data();
    audit('export', strtoupper($format));
    if ($format === 'csv') {
        $body = export_csv($data);
        header('Content-Type: text/csv; charset=utf-8');
    } else {
        $body = json_encode(strip_stored($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        header('Content-Type: application/json; charset=utf-8');
    }
    header('Content-Disposition: attachment; filename="' . $base . '.' . $format . '"');
    echo $body;
    exit;
}

if ($format === 'zip') {
    if (!class_exists('ZipArchive')) {
        flash('error', 'PHP разширението „zip“ не е включено на сървъра. Включете го от cPanel → Select PHP Version → Extensions или използвайте HTML/CSV експорт.');
        redirect('export.php');
    }
    @set_time_limit(300);
    $data = export_data();
    $tmp = tempnam(sys_get_temp_dir(), 'mqz');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        flash('error', 'Неуспешно създаване на ZIP архив.');
        redirect('export.php');
    }
    $zip->addFromString('00_otgovori.html', export_html($data, true));
    $zip->addFromString('otgovori.csv', export_csv($data));
    $jsonData = strip_stored($data);
    $zip->addFromString('otgovori.json', (string)json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $missing = [];
    $all = $data['general_files'];
    foreach ($data['items'] as $it) {
        $all = array_merge($all, $it['files']);
    }
    foreach ($all as $f) {
        $p = MQ_UPLOADS . '/' . $f['stored'];
        if (is_file($p)) {
            $zip->addFile($p, $f['zip_path']);
        } else {
            $missing[] = $f['name'];
        }
    }
    if ($missing) {
        $zip->addFromString('LIPSVASHTI_FAILOVE.txt', "Следните файлове липсват на сървъра:\n" . implode("\n", $missing));
    }
    $zip->close();
    audit('export', 'ZIP, файлове: ' . count($all));
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $base . '.zip"');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$fileCount = (int)db()->query('SELECT COUNT(*) FROM attachments')->fetchColumn();
$fileBytes = (int)db()->query('SELECT COALESCE(SUM(size_bytes),0) FROM attachments')->fetchColumn();
page_header('Експорт', 'export');
?>
<section class="card narrow">
  <h1>Експорт на отговорите</h1>
  <p class="muted">Свалете пълния пакет и го изпратете за анализ. Качени файлове: <strong><?= $fileCount ?></strong> (<?= e(fmt_size($fileBytes)) ?>).</p>
  <div class="export-grid">
    <a class="export-item" href="export.php?format=zip">
      <strong>Пълен пакет (ZIP)</strong>
      <span>Отговори (HTML, CSV, JSON) + всички снимки и документи, подредени по въпроси. Препоръчително.</span>
    </a>
    <a class="export-item" href="export.php?format=html" target="_blank" rel="noopener">
      <strong>Отчет за печат / PDF</strong>
      <span>Отваря се в нов раздел. От браузъра: Печат → Запази като PDF.</span>
    </a>
    <a class="export-item" href="export.php?format=csv">
      <strong>Таблица (CSV за Excel)</strong>
      <span>Един ред на въпрос, разделител „;“, UTF-8.</span>
    </a>
    <a class="export-item" href="export.php?format=json">
      <strong>Данни (JSON)</strong>
      <span>Машинно четим формат.</span>
    </a>
  </div>
</section>
<?php page_footer();
