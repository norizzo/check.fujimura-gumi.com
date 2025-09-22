<?php
// data_fetch.php
require_once 'config.php'; // DB接続ファイル
require_once 'functions.php'; // sanitizeInput 関数など

// データベース接続
$conn = connectDB();

// フィルターフォームのデータ取得
$genba_id = isset($_GET['genba_id']) ? intval($_GET['genba_id']) : '';
$inspection_type_id = isset($_GET['inspection_type_id']) ? intval($_GET['inspection_type_id']) : '';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// フィルタ適用判定
$filter_applied = !empty($genba_id) && !empty($inspection_type_id) && !empty($month);

// 現場名のドロップダウン取得
$genba_sql = "SELECT genba_id, genba_name FROM genba_master WHERE finished != 1 ORDER BY genba_id ASC";
$genba_result = $conn->query($genba_sql);
if (!$genba_result) {
    die("現場名の取得に失敗しました: (" . $conn->errno . ") " . $conn->error);
}

// 点検種類取得関数
function getInspectionTypes($conn, $genba_id, $month)
{
    $sql = "
        SELECT DISTINCT it.type_id, it.name, it.category
        FROM inspections i
        JOIN inspection_result ir ON i.id = ir.inspection_id
        JOIN inspection_types it ON i.inspection_type_id = it.type_id
        WHERE i.genba_id = ?
          AND DATE_FORMAT(i.date, '%Y-%m') = ?
        ORDER BY it.category, it.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $genba_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $inspection_types = [];
    while ($row = $result->fetch_assoc()) {
        $inspection_types[$row['category']][] = [
            'id' => $row['type_id'],
            'name' => sanitizeInput($row['name'])
        ];
    }
    $stmt->close();
    return $inspection_types;
}

// 点検種類取得
$inspection_types = getInspectionTypes($conn, $genba_id, $month);

// 点検項目取得
$inspection_items = [];
if ($filter_applied) {
    $items_sql = "SELECT item_id, item_name FROM inspection_items WHERE inspection_type_id = ? ORDER BY item_id ASC";
    $stmt = $conn->prepare($items_sql);
    $stmt->bind_param("i", $inspection_type_id);
    $stmt->execute();
    $result_items = $stmt->get_result();
    while ($item = $result_items->fetch_assoc()) {
        $inspection_items[$item['item_id']] = $item['item_name'];
    }
    $stmt->close();
}

// 点検データ取得
$inspection_data = [];
if ($filter_applied) {
    $sql = "
        SELECT i.id AS inspection_id, i.date, ir.item_id, ir.result_value
        FROM inspections i
        JOIN inspection_result ir ON i.id = ir.inspection_id
        WHERE i.genba_id = ?
          AND i.inspection_type_id = ?
          AND DATE_FORMAT(i.date, '%Y-%m') = ?
        ORDER BY i.date ASC, ir.item_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $genba_id, $inspection_type_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $inspection_id = $row['inspection_id'];
        $item_id = $row['item_id'];
        $result_value = $row['result_value'];

        if (!isset($inspection_data[$date])) {
            $inspection_data[$date] = ['inspection_id' => $inspection_id, 'items' => []];
        }
        $inspection_data[$date]['items'][$item_id] = $result_value;
    }
    $stmt->close();
}
?>
