<?php
session_start();
// セッション変数をすべて解除
$_SESSION = array();
session_destroy();
header('Location: index.php');
exit;
?>