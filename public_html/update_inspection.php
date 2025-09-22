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

// 必須項目の確認
if (empty($date) || empty($genba_id) || empty($checker_id) || empty($inspection_type_id)) {
    die("すべての必須項目を入力してください。");
}

// 既存データの確認と更新
$checkSql = "SELECT id FROM inspections WHERE inspection_type_id = ? AND date = ? AND genba_id = ?";
$params = [$inspection_type_id, $date, $genba_id];

$stmt = $conn->prepare($checkSql);
$stmt->bind_param("sss", ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $inspection_id = $row['id'];
    $updateSql = "UPDATE inspections SET checker_id = ?, comments = ?, inspection_item_name = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssi", $checker_id, $comments, $inspection_item_name, $inspection_id);
    if (!$updateStmt->execute()) {
        die("Execute failed: (" . $updateStmt->errno . ") " . $updateStmt->error);
    }
    $updateStmt->close();
    $message = "データを更新しました。閉じるボタンを押してください。";
} else {
    $message = "更新するデータが見つかりませんでした。";
}
if ($stmt->execute()) {
    // 登録成功
    $response = ['status' => 'success', 'message' => '登録が完了しました。'];
    echo json_encode($response);
} else {
    // 登録失敗
    $response = ['status' => 'error', 'message' => '登録に失敗しました。' . $stmt->error];
    echo json_encode($response);
}

// 接続を閉じる
$stmt->close();
$conn->close();
$stmt->close();
closeDB($conn);

// モーダルを閉じるためのJavaScriptを出力
/* echo "<script>
    alert('{$message}');
    if (window.parent) {
        window.parent.location.reload();
    } else {
        alert('{$message}');
    }
</script>"; */

?>