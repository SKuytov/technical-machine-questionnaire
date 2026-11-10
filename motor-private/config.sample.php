<?php
/**
 * Копирайте този файл като config.php (в същата папка motor-private/)
 * и попълнете данните за базата от cPanel → MySQL Databases.
 *
 * НИКОГА не качвайте config.php в GitHub.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'cpaneluser_motorq',   // пълното име с cPanel префикса
        'user'    => 'cpaneluser_motorq',   // пълното потребителско име с префикса
        'pass'    => 'СМЕНЕТЕ_МЕ',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'title'           => 'Технически въпросник — мотор-редуктор',
        'machine_title'   => 'SEW-EURODRIVE R37 DRM100L12BE2/AL/C',
        'machine_serial'  => '01.7495439501.0001.17',
        'timezone'        => 'Europe/Sofia',
        'upload_max_mb'   => 20,       // макс. размер на един файл
        'session_name'    => 'MQSESSID',
        'session_idle_h'  => 8,        // автоматичен изход след неактивност (часове)
        // След първоначалната инсталация сменете на false:
        'install_enabled' => true,
    ],
];
