<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';

$conn = connectDB();

try {
    $conn->begin_transaction();

    $date = $_POST['date'] ?? null;
    $genba_id = $_POST['genba_id'] ?? null;
    $checker_id = $_POST['checker_id'] ?? null;
    $inspection_type_id = $_POST['inspection_type_id'] ?? '';
    $comments = $_POST['comments'] ?? '';
    $inspection_item_name = $_POST['inspection_item_name'] ?? '';
    $inspection_id = $_POST['inspection_id'] ?? null;

    if (empty($date) || empty($genba_id) || empty($checker_id) || empty($inspection_type_id)) {
        throw new Exception("すべての必須項目を入力してください。");
    }

    if ($inspection_id) {
        $updateSql = "UPDATE inspections 
                     SET checker_id = ?, comments = ?, inspection_item_name = ?,
                         date = ?, genba_id = ?, inspection_type_id = ?
                     WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ssssssi", 
            $checker_id, $comments, $inspection_item_name, 
            $date, $genba_id, $inspection_type_id, $inspection_id
        );
        
        if (!$updateStmt->execute()) {
            throw new Exception("更新に失敗しました: " . $updateStmt->error);
        }
        $updateStmt->close();
        $message = "データを更新しました。";
        
    } else {
        $allowNewRegistration = false;
        
        if (($inspection_type_id == '18' || $inspection_type_id == '30') && !empty($inspection_item_name)) {
            $allowNewRegistration = true;
        } else {
            $checkSql = "SELECT id FROM inspections 
                        WHERE inspection_type_id = ? AND date = ? AND genba_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("sss", $inspection_type_id, $date, $genba_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                $allowNewRegistration = true;
            }
            $checkStmt->close();
        }

        if (!$allowNewRegistration) {
            throw new Exception("同じ日付、現場、点検種別の組み合わせのデータが既に存在します。");
        }

        $insertSql = "INSERT INTO inspections 
                     (genba_id, date, time, checker_id, inspection_type_id, comments, inspection_item_name) 
                     VALUES (?, ?, CURRENT_TIME, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ississ", 
            $genba_id, $date, $checker_id, 
            $inspection_type_id, $comments, $inspection_item_name
        );
        
        if (!$insertStmt->execute()) {
            throw new Exception("登録に失敗しました: " . $insertStmt->error);
        }
        $inspection_id = $insertStmt->insert_id;
        $insertStmt->close();
        $message = "新しいデータを登録しました。";
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'item_') === 0) {
            $item_id = intval(str_replace('item_', '', $key));
            $upsertSql = "INSERT INTO inspection_result 
                         (inspection_id, item_id, result_value) 
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE result_value = VALUES(result_value)";
            
            $upsertStmt = $conn->prepare($upsertSql);
            $upsertStmt->bind_param("iis", $inspection_id, $item_id, $value);
            
            if (!$upsertStmt->execute()) {
                throw new Exception("点検結果の保存に失敗しました: " . $upsertStmt->error);
            }
            $upsertStmt->close();
        }
    }

    $conn->commit();

    echo "<script>
        if (window.parent) {
            window.parent.location.reload();
        } else {
            alert('{$message}');
        }
    </script>";

} catch (Exception $e) {
    $conn->rollback();
    die("エラーが発生しました: " . $e->getMessage());
} finally {
    closeDB($conn);
}