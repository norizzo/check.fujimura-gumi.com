<?php
require_once './php/config.php';
require_once './php/functions.php';
// require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once './php/auth_check';
$conn = connectDB();

// 現場マスターを取得
$sql_genba = "SELECT * FROM genba_master";
$genba_master_result = $conn->query($sql_genba);
$genba_master = [];
if ($genba_master_result) {
    while ($row = $genba_master_result->fetch_assoc()) {
        $genba_master[] = $row;
    }
}

// 点検種類を取得
$sql_inspection_type = "SELECT * FROM Inspection_Types";
$inspection_type_result = $conn->query($sql_inspection_type);
$inspection_types = [];
if ($inspection_type_result) {
    while ($row = $inspection_type_result->fetch_assoc()) {
        $inspection_types[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>点検情報編集</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ボタン間にマージンを追加 */
        .btn {
            margin-bottom: 5px;
        }

        /* レスポンシブ対応：ボタンを横に並べる */
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px; /* ボタン間の隙間 */
        }

        /* ボタンの最大幅を制限 */
        .btn {
            width: auto;
            max-width: 500px;
            flex: 1 1 auto; /* 横幅に応じてボタンサイズを調整 */
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">点検情報編集</h1>

        <form action="edit_record.php" method="GET" id="recordForm">
            <!-- 現場名選択 -->
            <div class="col-md-7">
                <div class="mb-3">
                    <label for="genba" class="form-label">現場名:</label>
                    <select class="form-select" name="genba" id="genba" required>
                        <option value="">-- 現場名を選択 --</option>
                        <?php foreach ($genba_master as $genba): ?>
                            <option value="<?= htmlspecialchars($genba['genba_name']) ?>"><?= htmlspecialchars($genba['genba_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <!-- 年月選択 -->
                <div class="mb-3">
                    <label for="month_year" class="form-label">年月:</label>
                    <select class="form-select" name="month_year" id="month_year" required>
                        <?php for ($i = 0; $i < 12; $i++): ?>
                            <?php $month = date('Y-m', strtotime("-$i month")); ?>
                            <option value="<?= $month ?>"><?= $month ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>    
                <!-- 点検種類ボタン表示 -->
                <div class="mb-3">
                    <label class="form-label">点検種類:</label>
                    <!-- <div class="btn-group" role="group" aria-label="点検種類"> -->
                        <?php foreach ($inspection_types as $type): ?>
                            <a href="edit_record.php?inspection_type=<?= htmlspecialchars($type['id']) ?>" class="btn btn-primary" >
                                <?= htmlspecialchars($type['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    <!-- </div> -->
                </div>

                
        </form>
        <div class="m-5"> </div>
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
closeDB($conn);
?>
