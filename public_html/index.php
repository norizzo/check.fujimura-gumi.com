<?php
session_start();

/* echo ini_get('open_basedir'); */
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>藤村組 - ログイン</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
        }
        .login-container {
            max-width: 400px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .company-logo {
            max-width: 200px;
            margin-bottom: 20px;
        }
        .btn-login {
            background-color: #004d99;
            border-color: #004d99;
            width: 100%;
            padding: 10px;
        }
        .btn-login:hover {
            background-color: #003366;
            border-color: #003366;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .alert {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="login-container">
            <div class="text-center mb-4">
                <img src="img/logo.png" alt="藤村組" class="company-logo">
                <h2 class="mb-3">ログイン</h2>
            </div>
            
            <form action="auth.php" method="post">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="ユーザー名" required style="ime-mode: active;">
                    <label for="username">ユーザー名</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="パスワード" required>
                    <label for="password">パスワード</label>
                </div>

                <button type="submit" class="btn btn-primary btn-login">ログイン</button>
            </form>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
