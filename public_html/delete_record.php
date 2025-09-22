<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$conn = connectDB();

if (isset($_POST['inspection_id'])) {
    $inspection_id = intval($_POST['inspection_id']);

    $sql = "DELETE FROM inspections WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $inspection_id);
        if ($stmt->execute()) {
            // 削除成功
            echo json_encode(['status' => 'success']);
        } else {
            // 削除失敗
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
    } else {
        // ステートメント準備失敗
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
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