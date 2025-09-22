<?php
session_start();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログインページ</title>
</head>
<body>
    <h1>ログイン</h1>
    <form action="./php/auth.php" method="post">
        <label for="username">ユーザー名:</label>
        <input type="text" id="username" name="username" required>
        <br>
        <label for="password">パスワード:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <button type="submit">ログイン</button>
    </form>

    <?php if (isset($_SESSION['message'])): ?>
        <p style="color: red;"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></p>
    <?php endif; ?>
</body>
</html>
