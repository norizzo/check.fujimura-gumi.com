<?php
// 必要なファイルを読み込む
require_once 'config.php';
require_once 'functions.php';

// データベースに接続
$conn = connectDB();

// GETパラメータからinspection_idを取得
$inspection_id = isset($_GET['inspection_id']) ? intval($_GET['inspection_id']) : 0;

if ($inspection_id <= 0) {
    echo json_encode(['success' => false, 'message' => '無効な点検IDです。']);
    exit();
}

// 点検の基本情報を取得
$inspectionSql = "
    SELECT 
        i.id AS inspection_id,
        i.date,
        i.time,
        c.checker_name,
        g.genba_name,
        it.name AS inspection_type
    FROM inspections i
    JOIN genba_master g ON i.genba_id = g.genba_id
    JOIN checker_master c ON i.checker_id = c.checker_id
    JOIN inspection_types it ON i.inspection_type_id = it.type_id
    WHERE i.id = ?
";

$stmt = $conn->prepare($inspectionSql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => '準備失敗: ' . $conn->error]);
    exit();
}
$stmt->bind_param("i", $inspection_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => '実行失敗: ' . $stmt->error]);
    exit();
}
$inspectionResult = $stmt->get_result();
if ($inspectionResult->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => '点検データが見つかりません。']);
    exit();
}
$inspection = $inspectionResult->fetch_assoc();
$stmt->close();

// 点検項目と結果を取得
$resultsSql = "
    SELECT ir.item_id, ii.item_name, ir.result_value
    FROM inspection_result ir
    JOIN inspection_items ii ON ir.item_id = ii.item_id
    WHERE ir.inspection_id = ?
";
$stmt = $conn->prepare($resultsSql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => '準備失敗: ' . $conn->error]);
    exit();
}
$stmt->bind_param("i", $inspection_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => '実行失敗: ' . $stmt->error]);
    exit();
}
$resultsResult = $stmt->get_result();

$items = [];
while ($row = $resultsResult->fetch_assoc()) {
    $items[] = [
        'item_id' => $row['item_id'],
        'item_name' => $row['item_name'],
        'result_value' => $row['result_value']
    ];
}
$stmt->close();

// JSON形式でデータを返す
echo json_encode([
    'success' => true,
    'data' => [
        'inspection_id' => $inspection['inspection_id'],
        'date' => $inspection['date'],
        'time' => $inspection['time'],
        'checker_name' => $inspection['checker_name'],
        'genba_name' => $inspection['genba_name'],
        'inspection_type' => $inspection['inspection_type'],
        'items' => $items
    ]
]);
?>
