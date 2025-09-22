<?php
header('Content-Type: application/json');

// config.php と functions.php をインクルード
require_once './php/config.php';
require_once './php/functions.php';

// データベース接続
$conn = connectDB();  // connectDB() を使用して接続する

// 接続エラーチェック
if ($conn->connect_error) {
    die(json_encode(['error' => 'データベース接続エラー']));
}

// POSTデータの受け取り
$data = json_decode(file_get_contents('php://input'), true);

// パラメータのサニタイズ
$date = $conn->real_escape_string($data['date']);
$genba = $conn->real_escape_string($data['genba']);
$item = $conn->real_escape_string($data['item']);

// 点検登録チェックのSQL
$sql = "SELECT COUNT(*) as count 
        FROM inspections 
        WHERE date = ? 
        AND genba = ? 
        AND inspection_item = ?";

// プリペアドステートメントを使用
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $date, $genba, $item);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// 既に登録されているかを返す
echo json_encode([
    'exists' => $row['count'] > 0
]);

$stmt->close();
$conn->close();
