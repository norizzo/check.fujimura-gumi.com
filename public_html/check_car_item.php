<?php
header('Content-Type: application/json');

// config.php と functions.php をインクルード
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';

// データベース接続
$conn = connectDB();  // connectDB() を使用して接続する

// 接続エラーチェック
if ($conn->connect_error) {
    die(json_encode(['error' => 'データベース接続エラー']));
}

// POSTデータの受け取り
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['date']) || !isset($data['genba']) || !isset($data['type']) || !isset($data['item'])) {
    die(json_encode(['error' => 'パラメータ不足']));
}

// パラメータのサニタイズ
$date = $conn->real_escape_string($data['date']);
$genba = $conn->real_escape_string($data['genba']);
$type = intval($data['type']); // inspection_type_id は数値型を想定
$item = $conn->real_escape_string($data['item']);

// 点検登録チェックのSQL
$sql = "SELECT COUNT(*) as count
        FROM inspections
        WHERE date = ?
        AND genba_id= ?
        AND inspection_type_id= ?
        AND inspection_item_name = ?";

// プリペアドステートメントを使用
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssis', $date, $genba, $type, $item); // 'ssis' に修正
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// 既に登録されているかを返す
echo json_encode([
    'exists' => $row['count'] > 0
]);

$stmt->close();
$conn->close();
