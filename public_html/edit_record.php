<?php
// エラーレポートを有効にする（デバッグ用。運用時は無効にしてください）
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';

// データベースに接続
$conn = connectDB();


// `inspection_type` が指定されていない、もしくは数値でない場合はリダイレクト
/* if (!isset($_GET['inspection_type']) || !is_numeric($_GET['inspection_type'])) {
    header("Location: inspection_top.php");
    exit();
} */
$inspection_date = $_GET['date'] ?? date('Y-m-d');
$inspection_type = intval($_GET['inspection_type']);
$selected_genba_id = $_GET['genba_id'] ?? null;
$inspection_id = $_GET['inspection_id'] ?? null;
// 点検名を取得するための SQL クエリを実行
$inspection_name = getInspectionName($conn, $inspection_type);
$selectedCheckerId = $_GET['checker_id'] ?? null;
// 現場情報とチェック担当者のデータを取得
$genbaResult = getGenbaData($conn);
$checkerResult = getCheckerData($conn);

$targetResult = null;
$inspection_id = $_GET['inspection_id'] ?? '';
$result_values_json = $_GET['result_values'] ?? '[]';
$result_values = json_decode($result_values_json, true);
$comment = $_GET['comment'] ?? '';

// commentの値を確認するために出力
/* echo "<pre>";
var_dump($result_values);
var_dump($inspection_id);
echo "</pre>"; */

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
    $inspection_name = "";

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
// 既存のコードの後に追加
$item_name = array_key_exists('inspection_id', $_GET) ? getItemName($conn, $_GET['inspection_id']) : '';
// アイテム名を取得するためのSQLクエリ
function getItemName($conn, $inspection_id)
{
    $item_name = '';
    if ($inspection_id) {
        // inspection_idに基づいてアイテム名を取得
        $sql = "SELECT inspection_item_name FROM inspections WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $inspection_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $item_name = $row['inspection_item_name'];
        }
        $stmt->close();
    }
    return $item_name;
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($inspection_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        // 既存のスクリプト

        // 削除確認関数を追加
        function confirmDelete(event) {
            event.preventDefault(); // フォームの自動送信を防ぐ

            // カスタマイズされた確認ダイアログ
            const result = confirm('本当にこのレコードを削除しますか？\n\nこの操作は取り消せません。');

            if (result) {
                // ユーザーがOKを選択した場合のみフォームを送信
                event.target.submit();
            }
        }
    </script>

</head>

<body>
    <div class="container">
        <div class="row align-items-center mb-3">
            <div class="col-auto me-auto">
                <h2 class="mt-3"><?php echo sanitizeInput($inspection_name); ?></h2>
            </div>
            <?php if (isset($inspection_id)): ?>
                <div class="col-auto">
                    <form action="delete_record.php" method="POST" onsubmit="confirmDelete(event)">
                        <input type="hidden" name="inspection_id" value="<?php echo $inspection_id; ?>">
                        <button type="submit" class="btn btn-danger">削除</button>
                    </form>
                </div>
            <?php endif; ?>
            <form action="update_record.php" method="POST">
                <input type="hidden" name="inspection_type_id" value="<?php echo $inspection_type; ?>">
                <input type="hidden" name="inspection_id" value="<?php echo $inspection_id; ?>">
                <div class="col-md-4 mb-2">
                    <label for="date" class="form-label">点検日</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($inspection_date); ?>" required readonly style="background-color: #f8f9fa; /* Light gray */">
                </div>
                <div class="col-md-4 mb-2">
                    <label for="checker" class="form-label">点検者</label>
                    <select id="checker" name="checker_id" class="form-select" required readonly>
                        <option value="" disabled selected style="color: red;">選択してください</option>
                        <?php while ($row = $checkerResult->fetch_assoc()) {
                            // $displayNameが現在のchecker_nameと一致している場合に`selected`を追加
                            var_dump($selectedCheckerId);
                            var_dump($row['checker_id']);
                            $isSelected = ($row['checker_id'] === $selectedCheckerId) ? 'selected' : '';
                        ?>
                            <option value="<?php echo intval($row['checker_id']); ?>" <?php echo $isSelected; ?>>
                                <?php echo htmlspecialchars($row['checker_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <?php
                // 現場名を取得
                $selectedGenbaName = '';
                $selectedGenbaId = '';

                while ($row = $genbaResult->fetch_assoc()) {
                    if ($row['genba_id'] == $selected_genba_id) {
                        $selectedGenbaName = sanitizeInput($row['genba_name']);
                        $selectedGenbaId = intval($row['genba_id']);
                        break;
                    }
                }
                ?>

                <div class="col-md-4 mb-4">
                    <label for="genba" class="form-label">現場名</label>
                    <!-- 変更不能なテキストボックス -->
                    <input id="genba" type="text" class="form-control" value="<?php echo $selectedGenbaName; ?>" required readonly style="background-color: #f8f9fa; /* Light gray */">

                    <!-- 隠しフィールドで選択された値を送信 -->
                    <input type="hidden" name="genba_id" value="<?php echo $selectedGenbaId; ?>">
                </div>



                <?php if ($inspection_type == 30 || $inspection_type == 18 && $item_name <> ""): ?>
                    <div class="col-md-4 mb-2">
                        <label for="inspection_item_name" class="form-label">点検対象名</label>
                        <input id="inspection_item_name" name="inspection_item_name" class="form-control" value="<?php echo htmlspecialchars($item_name); ?>" required readonly style="background-color: #f8f9fa; /* Light gray */">
                    </div>
                <?php endif; ?>
                <div class="col-md-4 mb-2">
                    <?php
                    // デバッグ用: $result_valuesの内容を確認
                    // echo "<pre>";
                    // print_r($result_values);
                    // echo htmlspecialchars($result_values['inspection_id']);
                    // echo "</pre>";
                    /* echo "<pre>";
                print_r($itemsResult->fetch_all(MYSQLI_ASSOC)); // $itemsResultの中身を配列で表示
                echo "</pre>"; */

                    // フォーム生成ループ
                    foreach ($itemsResult as $item) {
                        $item_id = $item['item_id'];
                        $item_name = htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8');
                        $sub_item = !empty($item['sub']) ? '<p class="text-secondary">' . htmlspecialchars($item['sub'], ENT_QUOTES, 'UTF-8') . '</p>' : '';

                        // 該当するアイテムの結果値を取得（存在しない場合はデフォルト値"〇"）
                        $current_value = $result_values[$item_id] ?? '〇';

                        // HTML出力開始
                        echo '<div class="mb-3">';
                        echo '<p>' . $sub_item . $item_name . '</p>';
                        echo '<div class="d-flex justify-content-end">';
                        echo '<div id="buttons" class="btn-group" role="group" aria-label="' . $item_name . '">';

                        // ラジオボタンの値（〇、×、ー）
                        $radio_values = ['〇', '×', 'ー'];
                        foreach ($radio_values as $value) {
                            $is_checked = ($current_value === $value) ? 'checked' : '';
                            $btn_class = ($value === '〇') ? 'btn-outline-warning' : (($value === '×') ? 'btn-outline-warning' : 'btn-outline-warning');

                            echo '<input type="radio" id="item_' . $item_id . '-' . $value . '" 
                name="item_' . $item_id . '" 
                value="' . $value . '" 
                class="btn-check" 
                required 
                ' . $is_checked . '>
              <label for="item_' . $item_id . '-' . $value . '" 
                class="btn btn-sm ' . $btn_class . ' rounded">' . $value . '</label>';
                        }

                        echo '</div>'; // btn-group
                        echo '</div>'; // d-flex
                        echo '</div>'; // mb-3
                    }
                    ?>

                </div>
                <div class="col-md-4 mb-2">
                    <label for="comment" class="form-label">備考</label>
                    <textarea id="comment" name="comments" class="form-control"><?php echo htmlspecialchars($comment); ?></textarea>
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
                            let alertMessage = `${date}：${genbaName}：${inspectionName}点検は既に登録済みです：項目${decodeURIComponent(itemName)}は既に登録済みです`;
                            alert(alertMessage);
                        }
                    })
                    .catch(error => console.error('エラーチェック中にエラーが発生しました:', error));
            }
        </script>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php
closeDB($conn);
?>