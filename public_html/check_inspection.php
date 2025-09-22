<?php
require_once 'config.php';
require_once 'functions.php';

$conn = connectDB();

if (isset($_POST['inspection_type_id']) && isset($_POST['date']) && isset($_POST['genba_id'])) {
    $inspection_type_id = intval($_POST['inspection_type_id']);
    $date = $_POST['date'];
    $genba_id = intval($_POST['genba_id']);

    $checkSql = "SELECT * FROM inspections WHERE inspection_type_id = ? AND date = ? AND genba_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("isi", $inspection_type_id, $date, $genba_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'exists']);
    } else {
        echo json_encode(['status' => 'not_exists']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error']);
}

closeDB($conn);
?>
