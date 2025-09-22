<?php
if ($_POST['action'] === 'getInspectionItems') {
    $genbaId = intval($_POST['genba_id']);
    $inspectionTypeId = intval($_POST['inspection_type_id']);

    $sql = "SELECT name FROM inspection_items WHERE genba_id = ? AND inspection_type_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $genbaId, $inspectionTypeId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = ['name' => $row['name']];
    }

    echo json_encode($items);
    exit;
}

?>
