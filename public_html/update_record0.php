<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必要なファイルの読み込み
require_once 'config.php';
require_once 'functions.php';

// データベース接続
$conn = connectDB();

// POSTデータの取得
$date = $_POST['date'] ?? null;
$genba_id = $_POST['genba_id'] ?? null;
$checker_id = $_POST['checker_id'] ?? null;
$inspection_type_id = $_POST['inspection_type_id'] ?? '';
$comments = $_POST['comments'] ?? '';
$inspection_item_name = $_POST['inspection_item_name'] ?? '';
$inspection_id = $_POST['inspection_id'] ?? null;
var_dump($inspection_id);
// 必須項目の確認
if (empty($date) || empty($genba_id) || empty($checker_id) || empty($inspection_type_id)) {
    die("すべての必須項目を入力してください。");
}

// 既存データの確認
$checkSql = "SELECT id FROM inspections WHERE inspection_type_id = ? AND date = ? AND genba_id = ?";
$params = [$inspection_type_id, $date, $genba_id];

$stmt = $conn->prepare($checkSql);
$stmt->bind_param("sss", ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // 既存の inspections データが存在する場合は更新
    $row = $result->fetch_assoc();
    $inspection_id = $row['id'];

    $updateSql = "UPDATE inspections SET checker_id = ?, comments = ?, inspection_item_name = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssi", $checker_id, $comments, $inspection_item_name, $inspection_id);
    if (!$updateStmt->execute()) {
        die("Execute failed: (" . $updateStmt->errno . ") " . $updateStmt->error);
    }
    $updateStmt->close();
    $message = "データを更新しました。";
} else {
    // 既存の inspections データがない場合は新規作成
    $insertSql = "INSERT INTO inspections (genba_id, date, time, checker_id, inspection_type_id, comments, inspection_item_name) 
                  VALUES (?, ?, CURRENT_TIME, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("ississ", $genba_id, $date, $checker_id, $inspection_type_id, $comments, $inspection_item_name);
    if (!$insertStmt->execute()) {
        die("Execute failed: (" . $insertStmt->errno . ") " . $insertStmt->error);
    }
    $inspection_id = $insertStmt->insert_id; // 新しく作成されたレコードのIDを取得
    $insertStmt->close();
    $message = "新しいデータを挿入しました。";
}

// 点検結果の処理
foreach ($_POST as $key => $value) {
    if (strpos($key, 'item_') === 0) {
        $item_id = intval(str_replace('item_', '', $key));

        // inspection_result テーブルで対象データの存在確認
        $checkItemSql = "SELECT result_id FROM inspection_result WHERE inspection_id = ? AND item_id = ?";
        $checkItemStmt = $conn->prepare($checkItemSql);
        $checkItemStmt->bind_param("ii", $inspection_id, $item_id);
        $checkItemStmt->execute();
        $checkItemStmt->store_result();

        if ($checkItemStmt->num_rows > 0) {
            // レコードが存在する場合は更新
            $updateResultSql = "UPDATE inspection_result SET result_value = ? WHERE inspection_id = ? AND item_id = ?";
            $updateResultStmt = $conn->prepare($updateResultSql);
            $updateResultStmt->bind_param("sii", $value, $inspection_id, $item_id);
            if (!$updateResultStmt->execute()) {
                throw new Exception("Execute failed: (" . $updateResultStmt->errno . ") " . $updateResultStmt->error);
            }
            $updateResultStmt->close();
        } else {
            // レコードが存在しない場合は挿入
            $insertResultSql = "INSERT INTO inspection_result (inspection_id, item_id, result_value) VALUES (?, ?, ?)";
            $insertResultStmt = $conn->prepare($insertResultSql);
            $insertResultStmt->bind_param("iis", $inspection_id, $item_id, $value);
            if (!$insertResultStmt->execute()) {
                throw new Exception("Execute failed: (" . $insertResultStmt->errno . ") " . $insertResultStmt->error);
            }
            $insertResultStmt->close();
        }
        $checkItemStmt->close();
    }
}

// データベース接続を閉じる
$stmt->close();
closeDB($conn);

// 完了メッセージを表示
// echo "<script>alert('{$message}');</script>";


// モーダルを閉じるためのJavaScriptを出力
echo "<script>
    
    if (window.parent) {
        window.parent.location.reload();
    } else {
        alert('{$message}');
    }
</script>";

?>
