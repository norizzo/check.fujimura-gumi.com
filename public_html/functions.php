<?php
// 1つ目のデータベース接続
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("接続失敗: " . $conn->connect_error);
    }
    return $conn;
}

// 2つ目のデータベース接続
function connectSecondDB() {
    $conn = new mysqli(SECOND_DB_HOST, SECOND_DB_USER, SECOND_DB_PASS, SECOND_DB_NAME);
    if ($conn->connect_error) {
        die("2つ目のデータベース接続失敗: " . $conn->connect_error . " (" . $conn->connect_errno . ")");
    }
    return $conn;
}


function closeDB($conn) {
    $conn->close();
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

// エラーハンドリング関数
function handleError($message) {
    echo "<div style='color: red; font-weight: bold;'>エラー: " . $message . "</div>";
    // ログファイルにエラーを記録することもできます
    // error_log($message, 3, "error.log");
}
?>
