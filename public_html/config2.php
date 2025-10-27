<?php

if ($_SERVER['SERVER_ADDR'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1' || $_SERVER['SERVER_ADDR'] === '::1') {
    define('SECOND_DB_HOST', 'localhost');
    define('SECOND_DB_USER', 'root');
    define('SECOND_DB_PASS', '');
    define('SECOND_DB_NAME', '試作2');
    
} else {
    define('SECOND_DB_HOST', 'localhost');
    define('SECOND_DB_USER', 'fujimura_staffing');
    define('SECOND_DB_PASS', 'Staff2400');
    define('SECOND_DB_NAME', 'fujimura_staffing');
    
}
