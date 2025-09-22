<?php
require_once 'jwt.php';
require_once 'key.php';
session_start();

if (!isset($_SESSION['jwt'])) {
    header('Location: index.php');
    exit;
}

try {
    $token = $_SESSION['jwt'];
    $decoded = JWT::decode($token, JWT_SECRET_KEY);
    $displayName = $_SESSION['display_name'];
} catch (Exception $e) {
    $_SESSION['message'] = '認証が必要です。再度ログインしてください。';
    header('Location: index.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>home</title>
</head>
<body>
    <h1>ようこそ、<?php echo htmlspecialchars($displayName); ?>さん</h1>
    <p>ログインに成功しました。</p>
    <a href="logout.php">ログアウト</a>
</body>
</html>
