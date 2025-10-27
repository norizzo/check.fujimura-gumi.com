<?php
// get_inspection_types.php

require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';

// デバッグログの設定
error_log("get_inspection_types.php start", 0);

// DB接続
$conn = connectDB();
if (!$conn) {
    error_log("DB接続失敗", 0);
    echo '<option value="" disabled selected>データベース接続に失敗しました</option>';
    return;
}
error_log("DB接続成功", 0);

// フィルターフォームのデータ取得
$genba_id = isset($_GET['genba_id']) ? intval($_GET['genba_id']) : '';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

error_log("genba_id: " . print_r($genba_id, true), 0);
error_log("month: " . print_r($month, true), 0);

// 点検種類を取得する関数
function getInspectionTypes($conn, $genba_id, $month) {
    // 現場と月に基づいて点検データを取得
    $sql = "
        SELECT DISTINCT it.type_id, it.name, it.category
        FROM inspections i
        JOIN inspection_result ir ON i.id = ir.inspection_id
        JOIN inspection_types it ON i.inspection_type_id = it.type_id
        WHERE i.genba_id = ?
        AND DATE_FORMAT(i.date, '%Y-%m') = ?
        AND it.type_id != 10 AND it.type_id != 19
        ORDER BY it.category, it.name";

    error_log("SQLクエリ: " . $sql, 0);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("prepare()失敗: " . print_r($conn->error, true), 0);
        return [];
    }
    $stmt->bind_param("is", $genba_id, $month);
    if (!$stmt->execute()) {
        error_log("execute()失敗: " . print_r($stmt->error, true), 0);
        return [];
    }
    $result = $stmt->get_result();
    if (!$result) {
        error_log("get_result()失敗: " . print_r($stmt->error, true), 0);
        return [];
    }

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

// 点検種類を取得
$inspection_types = getInspectionTypes($conn, $genba_id, $month);
error_log("inspection_types: " . print_r($inspection_types, true), 0);


// HTML形式で点検種類を返す
$options = '<option value="" disabled selected>選択してください</option>';
if (!empty($inspection_types)) {
    foreach ($inspection_types as $category => $types) {
        $options .= "<optgroup label=\"" . sanitizeInput($category) . "\">";
        foreach ($types as $type) {
            $options .= "<option value=\"" . intval($type['id']) . "\">" . sanitizeInput($type['name']) . "</option>";
        }
        $options .= "</optgroup>";
    }
}
error_log("options: " . print_r($options, true), 0);
echo $options;
?>
