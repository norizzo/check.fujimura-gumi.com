<?php
// エラーレポート設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必要なファイルのインクルード
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once 'functions.php';
require_once 'config_staff.php';
require_once 'config.php';


// データベース接続
$conn = connectDB();

// 全期間の点検履歴を取得するSQLクエリ
$allInspectedSql = "
    SELECT genba_id, inspection_item_name, date
    FROM inspections
";
$allInspectedStmt = $conn->prepare($allInspectedSql);
$allInspectedStmt->execute();
$allInspectedResult = $allInspectedStmt->get_result();

$allInspectedItems = [];
while ($row = $allInspectedResult->fetch_assoc()) {
    $key = $row['genba_id'] . '_' . $row['inspection_item_name']; // genba_idとinspection_item_nameをキーにする
    $allInspectedItems[$key] = true;
}
$allInspectedStmt->close();


// 点検済みデータ取得（例：当日のデータ）
$date = date('Y-m-d');
$inspection_type_id = 30; // inspection_type_id を 30 に固定
$inspectedSql = "
    SELECT inspection_item_name, date
    FROM inspections
    WHERE date = ? AND inspection_type_id = ?
";
$stmt = $conn->prepare($inspectedSql);
$stmt->bind_param('si', $date, $inspection_type_id);
$stmt->execute();
$inspectedResult = $stmt->get_result();

$inspectedItems = [];
while ($row = $inspectedResult->fetch_assoc()) {
    $key = $row['date'] . '_' . $row['inspection_item_name']; // dateとinspection_item_nameをキーにする
    $inspectedItems[$key] = true; // inspection_item を格納
}
$stmt->close();
//echo "<script>console.log('inspectedItems:', " . json_encode($inspectedItems) . ");</script>";



$genbaSql = "SELECT genba_id, genba_name FROM genba_master WHERE finished = 0 ORDER BY genba_id ASC";
$genbaResult = $conn->query($genbaSql);
if (!$genbaResult) {
    die("Genba query failed: (" . $conn->errno . ") " . $conn->error);
}

// 点検者データ取得
$checkerSql = "SELECT checker_id, checker_name FROM checker_master WHERE hidden != 1 ORDER BY checker_phonetic ASC";
$checkerResult = $conn->query($checkerSql);
if (!$checkerResult) {
    die("Checker query failed: (" . $conn->errno . ") " . $conn->error);
}

// 発電機、溶接機データ取得
$targetSql = "SELECT id, category, name, abbreviation FROM target_name WHERE category IN ('発電機', '溶接機')";
$targetResult = $conn->query($targetSql);
if (!$targetResult) {
    die("target query failed: (" . $conn->errno . ") " . $conn->error);
}

// `inspection_type` に基づいてアイテムを取得する SQL クエリ
$itemsSql = "SELECT * FROM inspection_items WHERE inspection_type_id = 30";
$itemsResult = $conn->query($itemsSql);
if (!$itemsResult) {
    die("Query failed: (" . $conn->errno . ") " . $conn->error);
}
// $inspection_type = 18; // inspection_type_id を 18 に固定  -  既に上で定義済みなので削除

// 以下、不要になった2番目のDB接続関連のコードを削除

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ja">
    <meta name="description" content="社内用">
    <meta name="robots" content="index, follow">
    <title>発電機点検入力画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/inspection.css">

</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="container mt-5">

            <div class="row">
                <h2>発電機点検</h2>
                <form action="submit_inspection.php" method="POST" onsubmit="return validateForm()">
                    <input type="hidden" name="inspection_type_id" value="<?php echo $inspection_type_id; ?>">

                    <div class="col-md-4 mb-2">
                        <label for="date" class="form-label">点検日</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
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
                        <label for="genbaSelect" class="form-label">現場名を選択してください</label>
                        <select id="genbaSelect" name="genba_id" class="form-select" onchange="changeColorGenba(this)" required>
                            <option value="" selected disabled>選択してください</option>
                            <?php while ($row = $genbaResult->fetch_assoc()): ?>
                                <option value="<?php echo intval($row['genba_id']); ?>">
                                    <?php echo htmlspecialchars($row['genba_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div id="inspectionItems" class="col-md-8 mb-2">
                        <div id="genba-message"></div>
                    </div>
                </form>

            </div>

        </div>
    </main>
    <?php include 'footer.php'; ?>

    <script>
        const targetItems = <?php echo json_encode($targetResult->fetch_all(MYSQLI_ASSOC)); ?>;
        const inspectedItems = <?php echo json_encode($inspectedItems); ?>;
        const allInspectedItems = <?php echo json_encode($allInspectedItems); ?>;

        //デバッグコード：inspectedItemsをコンソールに出力
        //  console.log("inspectedItems from JS:", allInspectedItems);


        document.getElementById('genbaSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const selectedGenba = selectedOption.value;
            /* console.log("option:",selectedOption);
            console.log(selectedGenba); */
            if (selectedGenba) {
                // genbaMessage.textContent = `選択された現場: ${selectedOption.text}`;

                const inspectionItemsDiv = document.getElementById('inspectionItems');
                inspectionItemsDiv.innerHTML = '';
                // console.log("targetItems:", targetItems); // デバッグコード：targetItemsの内容を出力
                targetItems.forEach(item => {
                    // console.log("item:", item); // デバッグコード：各アイテムの内容を出力
                    const button = document.createElement('button');
                    const dateInput = document.getElementById('date');
                    const dropdownDate = dateInput.value.trim(); // YYYY-MM-DD形式で取得される
                    let itemKey = selectedGenba + '_' + item['name']; // genba_id をキーに追加
                    let dateKey = dropdownDate + '_' + item['name'];

                    button.className = 'btn m-1'; // Added Bootstrap class for width
                    button.innerText = item['name'];
                    button.type = 'button';
                    itemKey = itemKey.trim(); // 前後の空白や改行を削除
                    dateKey = dateKey.trim();
                    // itemKeyを生成


                    const isInspectedToday = inspectedItems[dateKey] !== undefined; // 当日点検済みかどうかをチェック

                    const hasHistoricalData = allInspectedItems[itemKey] !== undefined; // 過去に点検履歴があるかチェック
                    // console.log(hasHistoricalData);
                    /* console.log("中身:",inspectedItems[dateKey])
                    console.log("dateKey:", dateKey); // デバッグコード：itemKeyを出力
                    console.log("isInspectedToday:", isInspectedToday); // デバッグコード：isInspectedを出力
                    console.log("hasHistoricalData:", hasHistoricalData); */


                    if (isInspectedToday) {
                        button.classList.add('btn-inspected'); // 当日点検済みの場合はinspected
                    } else if (hasHistoricalData) {
                        button.classList.add('btn-historical'); // 履歴ありprimary
                    } else {
                        button.classList.add('btn-new'); // それ以外は青色のまま
                    }

                    if (!isInspectedToday) { // btn-inspectedでない場合のみイベントリスナーを追加
                        button.addEventListener('click', (event) => {
                            event.preventDefault();
                            const currentDate = new Date().toISOString().split('T')[0];

                            fetch('check_generator_item.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        date: currentDate,
                                        genba: selectedGenba,
                                        item: item['name']
                                    })
                                })
                                .then(response => response.json())
                                .then(result => {
                                    if (result.exists) {
                                        alert(`${currentDate}の${selectedGenba}の${item['name']}点検は既に登録されています。`);
                                    } else {
                                        displayInspectionForm(item['name']);
                                    }
                                })
                                .catch(error => {
                                    console.error('エラー:', error);
                                    displayInspectionForm(item['name']);
                                });
                        });
                    }

                    inspectionItemsDiv.appendChild(button);
                });

            } else {
                genbaMessage.textContent = '';
            }
        });

        // フォームを表示する関数
        function displayInspectionForm(itemName) {
            const formContainer = document.getElementById('inspectionItems');
            formContainer.innerHTML = `
            <div class="col-md-6 mb-2">
                <label for="target" class="form-label">点検対象</label>
                <select id="target" name="inspection_item_name" class="form-select" required>
                    <?php while ($row = $targetResult->fetch_assoc()) { ?>
                        <option value="<?php echo htmlspecialchars($row['name']); ?>">
                            <?php echo sanitizeInput($row['name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <input type="hidden" name="inspection_type_id" value="<?php echo $inspection_type_id; ?>">
            <div class="col-md-6 mb-2">
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

            <!-- コメント入力欄 -->
            <div class="col-md-4 mb-2">
                <label for="comments" class="form-label">コメント</label>
                <textarea id="comments" name="comments" class="form-control" rows="3"></textarea>
            </div>

            <!-- ファイルアップロード -->
            <div class="col-md-4 mb-2">
                <label for="file" class="form-label">写真アップロード</label>
                <input type="file" id="file" name="file" accept="image/*" class="form-control" onchange="handleFileSelection()">
            </div>
            <input type="hidden" name="source" value="generator">
            <div class="col-md-4 mb-2">
                <button type="submit" class="btn btn-primary">送信</button>
            </div>
        `;

            const selectElement = document.getElementById('target');
            const options = Array.from(selectElement.options);

            const existingOption = options.find(option => option.value === itemName);

            if (existingOption) {
                existingOption.selected = true;
            } else {
                const newOption = new Option(itemName, itemName, false, true);
                selectElement.add(newOption);
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const genbaId = urlParams.get('genba_id');
            const inspectionTypeId = urlParams.get('inspection_type_id');

            if (genbaId) {
                const genbaSelect = document.getElementById('genbaSelect');
                const matchingOption = Array.from(genbaSelect.options).find(option =>
                    option.value === genbaId
                );

                if (matchingOption) {
                    genbaSelect.value = genbaId;
                    genbaSelect.dispatchEvent(new Event('change'));
                } else {
                    console.error('一致する現場IDが見つかりませんでした');
                }
            }
        });

        function validateForm() {
            // 点検対象をトリム
            const inspectionItemName = document.getElementById('target').value.trim();

            // コメントをトリム
            const comments = document.getElementById('comments').value.trim();
            console.log("トリム後の点検対象:", inspectionItemName);
            console.log("トリム後のコメント:", comments);
            // もし点検対象が空の場合、エラーメッセージを表示
            if (inspectionItemName === "") {
                alert("点検対象を選択してください。");
                return false;
            }

            // 必要に応じて、コメントの内容を処理することもできます
            if (comments === "") {
                console.log("コメントが空です。必要に応じて処理を追加してください。");
            }

            // トリムしたデータをフォームに再設定して送信
            document.getElementById('target').value = inspectionItemName;
            document.getElementById('comments').value = comments;

            // フォームが送信される
            return true;
        }
    </script>

    <script src="./js/common.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>