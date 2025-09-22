<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// データベース接続設定
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

// データベース接続
$conn = new mysqli($servername, $username, $password, $dbname);

// 接続確認
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// POSTデータの処理
foreach ($_POST as $key => $value) {
    if (strpos($key, "name_") === 0) {
        $id = str_replace("name_", "", $key);
        $name = $conn->real_escape_string($value);
        
        if (empty($name)) {
            echo "エラー: 名前は必須です。";
            exit;
        }

        // col1～col20のデータを取得
        $columns = [];
        for ($i = 1; $i <= 20; $i++) {
            $columns["col$i"] = isset($_POST["category_" . $id . "_" . $i]) ? $conn->real_escape_string($_POST["category_" . $id . "_" . $i]) : "";
        }

        // データ更新SQLの作成
        $sql = "UPDATE data SET name='$name'";
        foreach ($columns as $colName => $colValue) {
            $sql .= ", $colName='$colValue'";
        }
        $sql .= " WHERE id=$id";

        // クエリ実行
        if ($conn->query($sql) === TRUE) {
            echo "データが正常に更新されました。";
        } else {
            echo "エラー: " . $conn->error;
            exit;
        }
    }
}

// データベース接続を閉じる
$conn->close();
?>


