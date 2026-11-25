<?php
declare(strict_types=1);

define('APP_NAME', 'Amazing Grace Beauty Salon');
define('APP_CURRENCY', 'GH₵');
define('APP_TIMEZONE', 'Africa/Accra');
date_default_timezone_set(APP_TIMEZONE);

define('DB_HOST', getenv('SALON_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('SALON_DB_NAME') ?: 'salon management');
define('DB_USER', getenv('SALON_DB_USER') ?: 'root');
define('DB_PASS', getenv('SALON_DB_PASS') ?: '');
define('DB_PORT', (int)(getenv('SALON_DB_PORT') ?: 3306));
define('APP_BASE_URL', rtrim(getenv('SALON_BASE_URL') ?: '', '/'));
