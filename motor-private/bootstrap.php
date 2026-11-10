<?php
/**
 * Общо зареждане: конфигурация, сесия, база данни, помощни функции.
 * Този файл се намира ИЗВЪН public_html и не е достъпен от браузъра.
 */
declare(strict_types=1);

define('MQ_PRIVATE', __DIR__);
define('MQ_UPLOADS', __DIR__ . '/uploads');
define('MQ_VERSION', '1.0.0');

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('Нужна е PHP 7.4 или по-нова версия (препоръчително 8.1+).');
}

$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit('Липсва motor-private/config.php. Копирайте config.sample.php като config.php и попълнете данните.');
}
$GLOBALS['MQ_CONFIG'] = require $cfgFile;

require_once __DIR__ . '/lib/questions.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';

date_default_timezone_set(mq_cfg('app.timezone', 'Europe/Sofia'));


mq_send_security_headers();
mq_start_session();
