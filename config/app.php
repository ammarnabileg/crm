<?php
define('SITE_NAME', 'مربح');
define('SITE_URL', 'http://localhost');
define('BRAND_COLOR', '#F5C518');
define('COMMISSION_RATE', 0.20);
define('ATTRIBUTION_WINDOW', 30); // days

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
