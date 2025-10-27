<?php
// 必要なファイルのインクルード
require_once dirname(__DIR__) . '/private/config.php'; // config.php のパスを調整
require_once dirname(__DIR__) . '/private/functions.php'; // functions.php のパスを調整

// データベース接続
$conn = connectDB();

// inspection_type_id を取得 (GETパラメータから)
$inspection_type_id = isset($_GET['inspection_type_id']) ? intval($_GET['inspection_type_id']) : 0;

// inspection_type_id が有効かチェック
if ($inspection_type_id <= 0) {
    http_response_code(400); // Bad Requestステータスコードを設定
    // エラーメッセージをJSON形式で出力
    echo json_encode(['error' => 'inspection_type_id が無効です']);
    exit; // スクリプトを終了
}

// 点検対象の取得 (例: target_name テーブルから)
$targetsSql = "SELECT name FROM target_name"; // テーブル名とカラム名を調整
$targetsStmt = $conn->prepare($targetsSql);
// $targetsStmt->bind_param('i', $inspection_type_id); //不要になったので削除
$targetsStmt->execute();
$targetsResult = $targetsStmt->get_result();

$targets = [];
while ($row = $targetsResult->fetch_assoc()) {
    $targets[] = ['name' => $row['name']]; // 'name' はテーブルのカラム名に合わせてください
}
$targetsStmt->close();


// 点検項目の取得 (例: inspection_items テーブルから)
// テーブル名を 'inspection_item_masters' に修正 (テーブル名が異なる可能性があるため)
$itemsSql = "SELECT item_id, item_name, sub FROM inspection_items WHERE inspection_type_id = ?"; // テーブル名とカラム名を調整
$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param('i', $inspection_type_id);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = [
        'item_id' => intval($row['item_id']),
        'item_name' => $row['item_name'],
        'sub' => $row['sub'] // 'sub_item' はテーブルのカラム名に合わせてください
    ];
}
$itemsStmt->close();


// レスポンスヘッダーをJSON形式に設定
header('Content-Type: application/json');
// 取得したデータをJSON形式で出力
echo json_encode(['targets' => $targets, 'items' => $items]);

$conn->close();
?>