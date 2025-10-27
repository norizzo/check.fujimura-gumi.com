<?php

if ($_SERVER['SERVER_ADDR'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1' || $_SERVER['SERVER_ADDR'] === '::1') {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', '試作2');
    
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'fujimura_staffing');
    define('DB_PASS', 'Staff2400');
    define('DB_NAME', 'fujimura_staffing');
    
}
