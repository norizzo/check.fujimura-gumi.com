<?php
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) . '/private/functions.php';

header('Content-Type: application/json');

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$conn = connectDB();
$filteredData = getFilteredData($conn, $date);

// genba_masterから全現場を取得
$genbaSql = "SELECT genba_id, genba_name FROM genba_master WHERE finished = 0 ORDER BY genba_id ASC";
$genbaResult = $conn->query($genbaSql);

$genbaList = [];
while ($row = $genbaResult->fetch_assoc()) {
    $genbaId = intval($row['genba_id']);
    // filteredDataに存在する現場のみ追加
    if (isset($filteredData[$genbaId])) {
        $genbaList[] = [
            'genba_id' => $genbaId,
            'genba_name' => $row['genba_name']
        ];
    }
}

closeDB($conn);

echo json_encode($genbaList, JSON_UNESCAPED_UNICODE);
?>
