<?php
// データベース接続
require_once dirname(__DIR__) . '/private/config.php'; // config.php のパスを調整
require_once dirname(__DIR__) . '/private/functions.php'; // functions.php のパスを調整

$inspection_type_id = $_GET['inspection_type_id'] ?? null;
$item_name = $_GET['item_name'] ?? null;

if ($inspection_type_id && $item_name) {
    $sql = "SELECT item_id, item_name FROM inspection_items WHERE inspection_type_id = 18";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $inspection_type_id, $item_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode($row); // JSON形式で返す
    } else {
        echo json_encode(['error' => 'データが見つかりません']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => '無効なリクエスト']);
}
?>
