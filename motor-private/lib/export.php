<?php
declare(strict_types=1);

function export_data(): array
{
    ensure_answer_rows();
    $questions = mq_questions();
    $answers = load_answers();
    $files = load_attachments();
    $d = dossier();
    $items = [];
    foreach ($questions as $qid => $q) {
        $a = $answers[$qid] ?? [];
        $items[] = [
            'id'         => $qid,
            'section'    => mq_sections()[$q['section']],
            'priority'   => $q['priority'],
            'question'   => $q['text'],
            'answer'     => (string)($a['answer_text'] ?? ''),
            'confidence' => mq_confidence_labels()[$a['confidence'] ?? ''] ?? '',
            'confidence_code' => (string)($a['confidence'] ?? ''),
            'updated_by' => $a['updated_by_name'] ?? null,
            'updated_at' => $a['updated_at'] ?? null,
            'version'    => (int)($a['version'] ?? 0),
            'files'      => array_map('export_file_entry', $files[$qid] ?? []),
        ];
    }
    return [
        'machine'      => mq_cfg('app.machine_title', ''),
        'serial'       => mq_cfg('app.machine_serial', ''),
        'status'       => mq_status_labels()[$d['status']],
        'status_code'  => $d['status'],
        'review_note'  => $d['review_note'],
        'exported_at'  => now(),
        'exported_by'  => current_user()['full_name'] ?? '',
        'items'        => $items,
        'general_files'=> array_map('export_file_entry', $files[0] ?? []),
    ];
}

function export_file_entry(array $f): array
{
    $qdir = (int)$f['question_id'] === 0 ? 'Obshti' : sprintf('Q%02d', (int)$f['question_id']);
    return [
        'id'          => (int)$f['id'],
        'name'        => $f['original_name'],
        'description' => $f['description'],
        'size'        => (int)$f['size_bytes'],
        'uploaded_by' => $f['uploaded_by_name'] ?? null,
        'uploaded_at' => $f['uploaded_at'],
        'stored'      => $f['stored_name'], // премахва се от JSON експорта
        'zip_path'    => 'failove/' . $qdir . '/' . (int)$f['id'] . '_' . safe_filename($f['original_name']),
    ];
}

function export_html(array $data, bool $forZip): string
{
    $css = 'body{font-family:Segoe UI,Arial,sans-serif;color:#1d2433;max-width:960px;margin:24px auto;padding:0 16px;line-height:1.5}'
        . 'h1{font-size:22px;margin:0 0 4px}h2{font-size:17px;margin:28px 0 8px;border-bottom:2px solid #0b5e7e;padding-bottom:4px;color:#0b5e7e}'
        . '.meta{color:#5b6475;font-size:13px}.q{border:1px solid #d9dee7;border-radius:8px;padding:12px 14px;margin:10px 0;page-break-inside:avoid}'
        . '.q h3{font-size:14.5px;margin:0 0 6px}.num{display:inline-block;min-width:26px;font-weight:700;color:#0b5e7e}'
        . '.prio{background:#fff3df;color:#8a4b00;border-radius:4px;padding:1px 6px;font-size:11px;margin-left:6px}'
        . '.ans{white-space:pre-wrap;background:#f6f8fb;border-radius:6px;padding:8px 10px;font-size:14px}.empty{color:#a0303a;font-style:normal}'
        . 'ul{margin:6px 0 0 18px;padding:0;font-size:13px}.warn{border-left:4px solid #b7791f;background:#fffaf0;padding:8px 12px;font-size:13px}'
        . '@media print{body{margin:0}a{color:inherit;text-decoration:none}}';
    $h = '<!doctype html><html lang="bg"><head><meta charset="utf-8"><title>Технически въпросник — ' . e($data['machine']) . '</title><style>' . $css . '</style></head><body>';
    $h .= '<h1>Технически въпросник — ' . e($data['machine']) . '</h1>';
    $h .= '<p class="meta">Сериен № ' . e($data['serial']) . ' · Статус: ' . e($data['status']) . ' · Експорт: ' . e(fmt_dt($data['exported_at'])) . ' (' . e($data['exported_by']) . ')</p>';
    if (!empty($data['review_note'])) {
        $h .= '<p class="warn"><strong>Бележка от прегледа:</strong> ' . nl2br(e($data['review_note'])) . '</p>';
    }
    $h .= '<p class="warn">Документът съдържа събрани данни от техническия екип. Не представлява инженерно одобрение на заместващ агрегат.</p>';
    $cur = '';
    foreach ($data['items'] as $it) {
        if ($it['section'] !== $cur) {
            $cur = $it['section'];
            $h .= '<h2>' . e($cur) . '</h2>';
        }
        $h .= '<div class="q"><h3><span class="num">' . $it['id'] . '.</span>' . e($it['question']) . ($it['priority'] ? '<span class="prio">Приоритет</span>' : '') . '</h3>';
        $h .= trim($it['answer']) !== '' ? '<div class="ans">' . e($it['answer']) . '</div>' : '<div class="ans empty">Няма отговор</div>';
        $meta = [];
        if ($it['confidence'] && $it['confidence_code'] !== '') { $meta[] = 'Достоверност: ' . e($it['confidence']); }
        if ($it['updated_at']) { $meta[] = e($it['updated_by'] ?? '—') . ', ' . e(fmt_dt($it['updated_at'])); }
        if ($meta) { $h .= '<p class="meta">' . implode(' · ', $meta) . '</p>'; }
        $h .= export_files_html($it['files'], $forZip);
        $h .= '</div>';
    }
    if ($data['general_files']) {
        $h .= '<h2>Общи файлове</h2><div class="q">' . export_files_html($data['general_files'], $forZip) . '</div>';
    }
    return $h . '</body></html>';
}

function export_files_html(array $files, bool $forZip): string
{
    if (!$files) {
        return '';
    }
    $h = '<ul>';
    foreach ($files as $f) {
        $href = $forZip ? $f['zip_path'] : 'download.php?id=' . $f['id'];
        $h .= '<li><a href="' . e($href) . '">' . e($f['name']) . '</a> (' . e(fmt_size($f['size'])) . ')'
            . ($f['description'] !== '' ? ' — ' . e($f['description']) : '') . '</li>';
    }
    return $h . '</ul>';
}

function export_csv(array $data): string
{
    $fh = fopen('php://temp', 'w+');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ['№', 'Раздел', 'Приоритет', 'Въпрос', 'Отговор', 'Достоверност', 'Последна промяна от', 'Дата', 'Брой файлове', 'Файлове'], ';');
    foreach ($data['items'] as $it) {
        fputcsv($fh, [
            $it['id'], $it['section'], $it['priority'] ? 'да' : '', $it['question'], $it['answer'],
            $it['confidence_code'] !== '' ? $it['confidence'] : '', $it['updated_by'] ?? '', $it['updated_at'] ? fmt_dt($it['updated_at']) : '',
            count($it['files']), implode(' | ', array_column($it['files'], 'zip_path')),
        ], ';');
    }
    rewind($fh);
    $out = stream_get_contents($fh);
    fclose($fh);
    return (string)$out;
}
