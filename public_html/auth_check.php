<?php
// auth_check.php
/* phpinfo(); */
@require_once dirname(__DIR__) . '/private/jwt.php';
require_once dirname(__DIR__) . '/private/key.php';
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';

session_start();

if (!isset($_SESSION['jwt'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        // モーダル内のiframeからのアクセスの場合
        echo "<script>
            window.parent.postMessage({ status: 'session_expired', message: 'セッションが切れました。ログインページにリダイレクトします。' }, '*');
        </script>";
        exit;
    } else {
        // 通常のページリクエストの場合
        header('Location: index.php');
        exit;
    }
}

try {
    $token = $_SESSION['jwt'];
    $decoded = JWT::decode($token, JWT_SECRET_KEY);
    $displayName = $_SESSION['display_name'];
} catch (Exception $e) {
    $_SESSION['message'] = '認証が必要です。再度ログインしてください。';
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        // モーダル内のiframeからのアクセスの場合
        echo "<script>
            window.parent.postMessage({ status: 'session_expired', message: 'セッションが切れました。ログインページにリダイレクトします。' }, '*');
        </script>";
        exit;
    } else {
        // 通常のページリクエストの場合
        header('Location: index.php');
        exit;
    }
}
?>
