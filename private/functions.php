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
//配車表のでーたを取得（smart_assignmentsテーブルから日付ベースで現場IDを取得）
function getFilteredData($conn2, $date = null) {
    // 日付が指定されていない場合は本日
    if ($date === null) {
        $date = date('Y-m-d');
    }

    // smart_assignmentsから指定日付の現場オブジェクト（target_name_id=NULL, genba_id存在）を取得
    $sql = "SELECT DISTINCT sa.genba_id, gm.genba_name
            FROM smart_assignments sa
            INNER JOIN genba_master gm ON sa.genba_id = gm.genba_id
            WHERE sa.assignment_date = ?
            AND sa.target_name_id IS NULL
            AND sa.genba_id IS NOT NULL
            ORDER BY sa.genba_id ASC";

    $stmt = $conn2->prepare($sql);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        error_log("getFilteredData SQL error: " . $conn2->error);
        return [];
    }

    $filteredData = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $genbaId = intval($row['genba_id']);
            $genbaName = $row['genba_name'];

            // genba_idをキーとして現場名を保存
            $filteredData[$genbaId] = [
                'genba_name' => $genbaName
            ];
        }
    }

    $stmt->close();
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
