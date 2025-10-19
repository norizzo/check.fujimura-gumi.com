<?php
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';
require_once 'auth_check.php';

$conn = connectDB();

if (isset($_POST['inspection_id'])) {
    $inspection_id = intval($_POST['inspection_id']);

    // トランザクション開始
    $conn->begin_transaction();
    try {
        // 削除前にinspectionsから情報を取得（smart_assignmentsフラグリセット用）
        $selectSql = "SELECT date, genba_id, inspection_item_name FROM inspections WHERE id = ?";
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->bind_param("i", $inspection_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $inspectionData = $result->fetch_assoc();
        $selectStmt->close();

        if (!$inspectionData) {
            throw new Exception("削除対象のinspectionが見つかりません");
        }

        // target_name_idを取得（inspection_item_nameから逆引き）
        $getTargetSql = "SELECT id FROM target_name WHERE short_name = ? LIMIT 1";
        $getTargetStmt = $conn->prepare($getTargetSql);
        $getTargetStmt->bind_param("s", $inspectionData['inspection_item_name']);
        $getTargetStmt->execute();
        $targetResult = $getTargetStmt->get_result();
        $targetData = $targetResult->fetch_assoc();
        $getTargetStmt->close();

        // inspections削除
        $sql = "DELETE FROM inspections WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $inspection_id);
        $stmt->execute();
        $stmt->close();

        // smart_assignmentsのフラグをリセット（target_name_idが取得できた場合のみ）
        if ($targetData && $targetData['id']) {
            $resetFlagSql = "UPDATE smart_assignments
                            SET inspection_completed = 0, updated_at = NOW()
                            WHERE assignment_date = ?
                            AND genba_id = ?
                            AND target_name_id = ?";
            $resetStmt = $conn->prepare($resetFlagSql);
            $resetStmt->bind_param("sii", $inspectionData['date'], $inspectionData['genba_id'], $targetData['id']);
            $resetStmt->execute();
            $affected_rows = $resetStmt->affected_rows;
            $resetStmt->close();

            error_log("smart_assignmentsフラグリセット: date={$inspectionData['date']}, genba_id={$inspectionData['genba_id']}, target_name_id={$targetData['id']}, affected_rows={$affected_rows}");
        } else {
            error_log("警告: target_name_idが見つからないため、smart_assignmentsフラグはリセットされませんでした。inspection_item_name={$inspectionData['inspection_item_name']}");
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("削除エラー: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'inspection_id not found']);
}

closeDB($conn);

echo "<script>
    
    if (window.parent) {
        window.parent.location.reload();
    } else {
        alert('{$message}');
    }
</script>";

?>