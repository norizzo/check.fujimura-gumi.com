<?php
require_once 'auth_check.php';

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- <script type="text/javascript" src="https://unpkg.com/gridjs/dist/gridjs.umd.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/gridjs/dist/theme/mermaid.min.css" /> -->
    <title><?php echo $page_title ?? '藤村組'; ?></title>
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .company-logo {
            max-height: 50px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-name {
            font-size: 0.9rem;
            color: #666;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.2s;
            font-size: 0.875rem;
        }
        .logout-btn:hover {
            background-color: #c82333;
            color: white;
            text-decoration: none;
        }
    </style>
    <?php if (isset($additional_styles)) echo $additional_styles; ?>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="/inspection_top.php"><img src="../img/logo.png" alt="藤村組" class="company-logo"></a>
                <div class="user-info">
                    <span class="user-name">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($_SESSION['display_name'] ?? ''); ?>
                    </span>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> ログアウト
                    </a>
                </div>
            </div>
        </div>
    </header>
