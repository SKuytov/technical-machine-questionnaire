<?php
/**
 * Път до частната папка motor-private (извън public_html).
 * По подразбиране: /home/ПОТРЕБИТЕЛ/public_html/motor-questionnaire  →  /home/ПОТРЕБИТЕЛ/motor-private
 * Ако сте я поставили другаде, сменете пътя тук, напр.:
 * define('MQ_PRIVATE_DIR', '/home/ПОТРЕБИТЕЛ/motor-private');
 */
define('MQ_PRIVATE_DIR', dirname(__DIR__, 2) . '/motor-private');

if (!is_file(MQ_PRIVATE_DIR . '/bootstrap.php')) {
    http_response_code(500);
    exit('Не е намерена папката motor-private. Проверете пътя в _paths.php.');
}
require MQ_PRIVATE_DIR . '/bootstrap.php';
