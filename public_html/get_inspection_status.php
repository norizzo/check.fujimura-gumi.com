<?php
/**
 * 検査状況取得API
 * 
 * GETパラメータ:
 *   genba_id: 現場ID
 *   date: 日付 (YYYY-MM-DD)
 * 
 * 返り値:
 *   JSON形式の配列。キーは "genba_id-inspection_type_id" の形式で、値は true (点検済み) または false (未点検)。
 */

 require_once dirname(__DIR__) . '/private/config.php';
 require_once dirname(__DIR__) .  '/private/functions.php';

// データベース接続
$conn = connectDB();

// GETパラメータ取得
$genbaId = $_GET['genba_id'];
$date = $_GET['date'];

// サーバー時間がUTCなので、日本時間に変換する
$date = date('Y-m-d', strtotime($date . ' +9 hours'));


// SQLクエリ作成。指定された現場IDと日付で点検済み項目を取得する。
$sql = "SELECT inspection_type_id FROM inspections WHERE genba_id = ? AND DATE(date) = ?";

// ステートメント準備
$stmt = $conn->prepare($sql);

// パラメータバインド
$stmt->bind_param("is", $genbaId, $date);

// クエリ実行
$stmt->execute();

// 結果取得
$result = $stmt->get_result();

// 点検済み項目を格納する配列
$inspectedItems = [];

// 結果セットからデータを取得し、配列に格納
while ($row = $result->fetch_assoc()) {
    $key = $genbaId . '-' . $row['inspection_type_id'] ;
    $inspectedItems[$key] = true;
}

// JSON形式で出力
echo json_encode($inspectedItems);

// ステートメントとデータベース接続を閉じる
$stmt->close();
closeDB($conn);
?>