<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/private/db_connection.php';
require_once dirname(__DIR__) . '/private/jwt.php';
require_once dirname(__DIR__) . '/private/key.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // usernameでユーザー情報を取得
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch();

    // パスワードを検証
    if ($user && password_verify($password, $user['password'])) {
        // JWTのペイロードに表示名も含める
        $payload = [
            'sub' => $user['id'],
            'name' => $user['username'],
            'display' => $user['display'],
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $token = JWT::encode($payload, JWT_SECRET_KEY);

        // セッションにトークンと表示名を保存
        $_SESSION['jwt'] = $token;
        $_SESSION['display_name'] = $user['display'];

        header('Location: inspection_top.php');
        exit;
    } else {
        $_SESSION['message'] = 'ユーザー名またはパスワードが正しくありません。';
        header('Location: index.php');
        exit;
    }
}
?>
