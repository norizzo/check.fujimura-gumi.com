<?php
// エラーレポートを有効にする（デバッグ用。運用時は無効にしてください）
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// データベースに接続
$conn = connectDB();

// `inspection_type` が指定されていない、もしくは数値でない場合はリダイレクト
if (!isset($_GET['inspection_type']) || !is_numeric($_GET['inspection_type'])) {
    header("Location: inspection_top.php");
    exit();
}
$inspection_date = $_GET['date'] ?? date('Y-m-d');
$inspection_type = intval($_GET['inspection_type']);
$selected_genba_id = $_GET['genba_id'] ?? null;
// 点検名を取得するための SQL クエリを実行
$inspection_name = getInspectionName($conn, $inspection_type);

// 現場情報とチェック担当者のデータを取得
$genbaResult = getGenbaData($conn);
$checkerResult = getCheckerData($conn);
$targetResult = null;

if ($inspection_type == 30) {
    $targetResult = getTargetData($conn);
}

// アイテムを取得する SQL クエリ
$itemsResult = getInspectionItems($conn, $inspection_type);

function getInspectionName($conn, $inspection_type)
{
    $nameSql = "SELECT name FROM inspection_types WHERE type_id = ?";
    $stmt = $conn->prepare($nameSql);
    if (!$stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $stmt->bind_param("i", $inspection_type);
    if (!$stmt->execute()) {
        die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }
    
    // 変数の初期化
    $inspection_name = null;
    
    $stmt->bind_result($inspection_name);
    if (!$stmt->fetch()) {
        die("No inspection type found with ID: " . sanitizeInput($inspection_type));
    }
    $stmt->close();
    
    // 正常にデータが取得できた場合のみ返す
    return $inspection_name;
}

function getGenbaData($conn)
{
    $genbaSql = "SELECT genba_id, genba_name FROM genba_master WHERE finished != 1 ORDER BY genba_id ASC";
    $genbaResult = $conn->query($genbaSql);
    if (!$genbaResult) {
        die("Genba query failed: (" . $conn->errno . ") " . $conn->error);
    }
    return $genbaResult;
}

function getCheckerData($conn)
{
    $checkerSql = "SELECT checker_id, checker_name FROM checker_master WHERE hidden != 1 ORDER BY checker_phonetic ASC";
    $checkerResult = $conn->query($checkerSql);
    if (!$checkerResult) {
        die("Checker query failed: (" . $conn->errno . ") " . $conn->error);
    }
    return $checkerResult;
}

function getTargetData($conn)
{
    $targetSql = "SELECT name FROM target_name WHERE category = '発電機' OR category = '溶接機'";
    $targetResult = $conn->query($targetSql);
    if (!$targetResult) {
        die("Target query failed: (" . $conn->errno . ") " . $conn->error);
    }
    return $targetResult;
}

function getInspectionItems($conn, $inspection_type)
{
    $itemsSql = "SELECT * FROM inspection_items WHERE inspection_type_id = ?";
    $stmt = $conn->prepare($itemsSql);
    if (!$stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $stmt->bind_param("i", $inspection_type);
    if (!$stmt->execute()) {
        die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }
    $itemsResult = $stmt->get_result();
    if (!$itemsResult) {
        die("Get result failed: (" . $stmt->errno . ") " . $stmt->error);
    }
    return $itemsResult;
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($inspection_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mt-5"><?php echo sanitizeInput($inspection_name); ?></h2>

        <form action="submit_inspection.php" method="POST">
            <input type="hidden" name="inspection_type_id" value="<?php echo $inspection_type; ?>">
            <div class="col-md-4 mb-2">
                <label for="date" class="form-label">点検日</label>

                <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($inspection_date); ?>" required>
            </div>
            <div class="col-md-4 mb-2">
                <label for="checker" class="form-label">点検者</label>
                <select id="checker" name="checker_id" class="form-select" required>
                    <option value="" disabled selected>選択してください</option>
                    <?php while ($row = $checkerResult->fetch_assoc()) {
                        // $displayNameが現在のchecker_nameと一致している場合に`selected`を追加
                        $isSelected = ($row['checker_name'] === $displayName) ? 'selected' : '';
                    ?>
                        <option value="<?php echo intval($row['checker_id']); ?>" <?php echo $isSelected; ?>>
                            <?php echo htmlspecialchars($row['checker_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <label for="genba" class="form-label">現場名</label>
                <select id="genba" name="genba_id" class="form-select" required onchange="checkInspection()">
                    <option value="" disabled selected>選択してください</option>
                    <?php while ($row = $genbaResult->fetch_assoc()) {
                        $isSelected = ($row['genba_id'] == $selected_genba_id) ? 'selected' : '';
                    ?>
                        <option value="<?php echo intval($row['genba_id']); ?>" <?php echo $isSelected; ?>>
                            <?php echo sanitizeInput($row['genba_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <?php if ($inspection_type == 30 && $targetResult && $targetResult->num_rows > 0): ?>
                <div class="col-md-4 mb-2">
                    <label for="inspection_item_name" class="form-label">点検対象名</label>
                    <select id="inspection_item_name" name="inspection_item_name" class="form-select" required onchange="checkItemName()">
                        <option value="" disabled selected>選択してください</option>
                        <?php while ($row = $targetResult->fetch_assoc()) { ?>
                            <option value="<?php echo htmlspecialchars($row['name']); ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-4 mb-2">
                <?php while ($row = $itemsResult->fetch_assoc()) { ?>
                    <div class="mb-3">
                        <label class="form-label">
                            <?php if (!empty($row['sub'])) { ?>
                                <p class="text-secondary"><?php echo sanitizeInput($row['sub']); ?></p>
                            <?php } ?>
                            <?php echo sanitizeInput($row['item_name']); ?>
                        </label>
                        <div class="d-flex justify-content-end">
                            <div class="btn-group" role="group" aria-label="<?php echo sanitizeInput($row['item_name']); ?>">
                                <input type="radio" id="item_<?php echo intval($row['item_id']); ?>-1" name="item_<?php echo intval($row['item_id']); ?>" value="〇" required class="btn-check" checked>
                                <label for="item_<?php echo intval($row['item_id']); ?>-1" class="btn btn-sm btn-outline-warning rounded">〇</label>

                                <input type="radio" id="item_<?php echo intval($row['item_id']); ?>-2" name="item_<?php echo intval($row['item_id']); ?>" value="×" class="btn-check">
                                <label for="item_<?php echo intval($row['item_id']); ?>-2" class="btn btn-sm btn-outline-warning rounded">×</label>

                                <input type="radio" id="item_<?php echo intval($row['item_id']); ?>-3" name="item_<?php echo intval($row['item_id']); ?>" value="ー" class="btn-check">
                                <label for="item_<?php echo intval($row['item_id']); ?>-3" class="btn btn-sm btn-outline-warning rounded">ー</label>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="col-md-4 mb-2">
                <label for="comments" class="form-label">コメント</label>
                <textarea id="comments" name="comments" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-md-4 mb-2">
                <label for="file" class="form-label">写真アップロード</label>
                <input type="file" id="file" name="file" accept="image/*" class="form-control" onchange="handleFileSelection()">
            </div>
            <input type="hidden" name="source" value="inspection_form">
            <div class="col-md-4 mb-2">
                <button type="submit" class="btn btn-primary">送信</button>
            </div>
        </form>
    </div>

    <script>
        function handleFileSelection() {
            const input = document.getElementById('file');
            const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);

            if (isMobile) {
                input.setAttribute('capture', 'camera'); // スマホの場合はカメラをデフォルトに設定
            } else {
                input.removeAttribute('capture'); // PCの場合はカメラを使用しない
            }
        }


        function checkInspection() {
            const genbaSelect = document.getElementById('genba');
            const genbaId = genbaSelect.value;
            const date = document.getElementById('date').value;
            const inspectionTypeId = <?php echo $inspection_type; ?>;
            const genbaName = genbaSelect.options[genbaSelect.selectedIndex].text; // 選択された現場名を取得
            const inspectionName = "<?php echo addslashes($inspection_name); ?>";

            if (!genbaId || !date) {
                return; // 必要な情報が入力されていない場合はスキップ
            }

            let itemName = '';
            if (inspectionTypeId === 18 || inspectionTypeId === 30) {
                const itemNameSelect = document.getElementById('inspection_item_name');
                if (!itemNameSelect) {
                    return; // itemNameのドロップダウンが存在しない場合はスキップ
                }
                itemName = encodeURIComponent(itemNameSelect.value);
                if (!itemName) {
                    return; // itemNameが選択されていない場合はスキップ
                }
            }

            fetch('check_inspection.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `inspection_type_id=${inspectionTypeId}&date=${encodeURIComponent(date)}&genba_id=${genbaId}&inspection_item_name=${itemName}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'exists') {
                        let alertMessage = `${date}：${genbaName}：${inspectionName}点検は既に登録済みです`;
                        if (inspectionTypeId === 18 || inspectionTypeId === 30) {
                            alertMessage += `：項目${decodeURIComponent(itemName)}は既に登録済みです`;
                        }
                        alert(alertMessage);
                    }
                })
                .catch(error => console.error('エラーチェック中にエラーが発生しました:', error));
        }

        function checkItemName() {
            const genbaSelect = document.getElementById('genba');
            const genbaId = genbaSelect.value;
            const date = document.getElementById('date').value;
            const inspectionTypeId = <?php echo $inspection_type; ?>;
            const genbaName = genbaSelect.options[genbaSelect.selectedIndex].text; // 選択された現場名を取得
            const inspectionName = "<?php echo addslashes($inspection_name); ?>";

            if (!genbaId || !date) {
                return; // 必要な情報が入力されていない場合はスキップ
            }

            const itemNameSelect = document.getElementById('inspection_item_name');
            if (!itemNameSelect) {
                return; // itemNameのドロップダウンが存在しない場合はスキップ
            }
            const itemName = encodeURIComponent(itemNameSelect.value);
            if (!itemName) {
                return; // itemNameが選択されていない場合はスキップ
            }

            /* fetch('check_inspection.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `inspection_type_id=${inspectionTypeId}&date=${encodeURIComponent(date)}&genba_id=${genbaId}&inspection_item_name=${itemName}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'exists') {
                        let alertMessage = `${date}：${genbaName}：${inspectionName}点検は既に登録済みです：項目${decodeURIComponent(itemName)}は既に登録済みです`;
                        alert(alertMessage);
                    }
                })
                .catch(error => console.error('エラーチェック中にエラーが発生しました:', error));
        } */
    </script>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php
closeDB($conn);
?>