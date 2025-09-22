<?php
require_once 'config.php';
require_once 'functions.php';

// データベースに接続
$conn = connectDB();

// パラメータを取得
$genba_id = $_GET['genba_id'] ?? null;
$inspection_type = $_GET['inspection_type'] ?? null;
$date = $_GET['date'] ?? null;

// データを取得するSQLクエリ
$sql = "SELECT 
    i.checker_id, 
    i.comments, 
    ii.item_id, 
    ii.result
FROM 
    inspections i
JOIN 
    inspection_details ii ON i.inspection_id = ii.inspection_id
WHERE 
    i.genba_id = ? AND 
    i.inspection_type_id = ? AND 
    i.date = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $genba_id, $inspection_type, $date);
$stmt->execute();
$result = $stmt->get_result();

$existing_data = [
    'checker_id' => null,
    'comments' => null,
    'items' => []
];

while ($row = $result->fetch_assoc()) {
    $existing_data['checker_id'] = $row['checker_id'];
    $existing_data['comments'] = $row['comments'];
    $existing_data['items'][$row['item_id']] = $row['result'];
}

$stmt->close();
$conn->close();

// JSON形式で出力
header('Content-Type: application/json');
echo json_encode($existing_data);