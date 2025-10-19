<?php
// 1つ目のデータベース接続
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("接続失敗: " . $conn->connect_error);
    }
    return $conn;
}

// 2つ目のデータベース接続
/* function connectSecondDB() {
    $conn = new mysqli(SECOND_DB_HOST, SECOND_DB_USER, SECOND_DB_PASS, SECOND_DB_NAME);
    if ($conn->connect_error) {
        die("2つ目のデータベース接続失敗: " . $conn->connect_error . " (" . $conn->connect_errno . ")");
    }
    return $conn;
} */


function closeDB($conn) {
    $conn->close();
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

// エラーハンドリング関数
function handleError($message) {
    echo "<div style='color: red; font-weight: bold;'>エラー: " . $message . "</div>";
    // ログファイルにエラーを記録することもできます
    // error_log($message, 3, "error.log");
}
//配車表のでーたを取得
function getFilteredData($conn2) {
    $sql = "SELECT name, top_y FROM sortable WHERE type_id=3 AND top_y < 827 AND left_x BETWEEN 1 AND 10 ORDER BY top_y ASC";
    $result = $conn2->query($sql);

    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        echo "データが見つかりませんでした。";
        return null; // or handle error appropriately
    }

    $filteredData = [];
    for ($i = 0; $i < count($data); $i++) {
        if ($data[$i]['top_y'] >= 55 && $data[$i]['top_y'] <= 60) {
            $startTopY = 54;
        } else {
            $startTopY = $data[$i]['top_y'] - 2;
        }

        if ($i + 1 < count($data)) {
            $endTopY = $data[$i + 1]['top_y'] - 5;
        } else {
            $endTopY = $data[$i]['top_y'] + 140;
        }

      /*   // top_yとend_yをモニタ
        var_dump($startTopY);
        var_dump($endTopY); */


        $keyName = mb_convert_kana(str_replace([" ", "\n", "\r", "\t"], "", $data[$i]['name']), "KV", "UTF-8");

        $nameSql = "SELECT name FROM sortable WHERE top_y >= ? AND top_y < ? AND left_x BETWEEN 1 AND 1390 AND type_id != 3 AND type_id IN (5,12)";
        $stmt = $conn2->prepare($nameSql);
        $stmt->bind_param("ii", $startTopY, $endTopY);
        $stmt->execute();
        $nameResult = $stmt->get_result();

        $nameArray = [];
        while ($nameRow = $nameResult->fetch_assoc()) {
            $nameArray[] = mb_convert_kana(str_replace([" ", "\n", "\r", "\t"], "",$nameRow['name']), "KV", "UTF-8");
        }
        $stmt->close();

        $filteredData[$keyName] = $nameArray;
    }
    return $filteredData;
}

// smart_assignmentsから日付ベースで重機配置データを取得
function getAssignmentsForInspection($conn, $date = null) {
    // 日付が指定されていない場合は当日
    if ($date === null) {
        $date = date('Y-m-d');
    }

    $sql = "SELECT
                gm.genba_name,
                tn.short_name as machine_name,
                sa.assignment_id,
                sa.genba_id,
                sa.target_name_id
            FROM smart_assignments sa
            INNER JOIN genba_master gm ON sa.genba_id = gm.genba_id
            INNER JOIN target_name tn ON sa.target_name_id = tn.id
            WHERE sa.assignment_date = ?
            AND sa.assignment_status = 'active'
            AND tn.ob_type IN (5, 12)
            ORDER BY gm.genba_id, tn.short_name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();

    error_log("getAssignmentsForInspection: 日付={$date}, 取得件数=" . $result->num_rows);

    $filteredData = [];
    while ($row = $result->fetch_assoc()) {
        $genbaId = $row['genba_id'];
        $machineName = mb_convert_kana(str_replace([" ", "\n", "\r", "\t"], "", $row['machine_name']), "KV", "UTF-8");
        $targetNameId = $row['target_name_id'];

        // genba_idをキーとして使用
        if (!isset($filteredData[$genbaId])) {
            $filteredData[$genbaId] = [
                'genba_name' => $row['genba_name'],
                'machines' => []
            ];
        }
        // 重機名とtarget_name_idを両方保存
        $filteredData[$genbaId]['machines'][] = [
            'name' => $machineName,
            'target_name_id' => $targetNameId
        ];

        error_log("  genba_id={$genbaId}, genba_name={$row['genba_name']}, machine={$machineName}, target_name_id={$targetNameId}");
    }
    $stmt->close();

    return $filteredData;
}
?>
