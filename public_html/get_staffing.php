<?php
// エラーレポート設定
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

// 必要なファイルのインクルード
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once 'functions.php';
require_once 'config_staff.php';
require_once 'config.php';


// データベース接続
$conn = connectDB();

// 点検済みデータ取得（例：当日のデータ）
$date = date('Y-m-d');
$inspection_type_id = 18; // inspection_type_id を 18 に固定
$inspectedSql = "
    SELECT genba_id, inspection_item_name
    FROM inspections
    WHERE date = ? AND inspection_type_id = ?
";
$stmt = $conn->prepare($inspectedSql);
$stmt->bind_param('si', $date, $inspection_type_id);
$stmt->execute();
$inspectedResult = $stmt->get_result();

$inspectedItems = [];
while ($row = $inspectedResult->fetch_assoc()) {
    $key = $row['genba_id'] . '-' . $row['inspection_item_name'];
    $inspectedItems[$key] = true; // inspection_id を格納
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
// 点検対象データ取得
$targetSql = "SELECT name FROM target_name WHERE category!= '発電機' AND category!= '溶接機'";
$targetResult = $conn->query($targetSql);
if (!$targetResult) {
    die("target query failed: (" . $conn->errno . ") " . $conn->error);
}
// `inspection_type` に基づいてアイテムを取得する SQL クエリ
$itemsSql = "SELECT * FROM inspection_items WHERE inspection_type_id = 18";
$itemsResult = $conn->query($itemsSql);
if (!$itemsResult) {
    die("Query failed: (" . $conn->errno . ") " . $conn->error);
}
// $inspection_type = 18; // inspection_type_id を 18 に固定  -  既に上で定義済みなので削除

// データベース接続（2番目のDB接続）
// type_id=3のtop_yとnameを昇順で取得し、top_y < 780 のものだけに限定
$conn2 = connectSecondDB();
$sql = "SELECT name, top_y FROM sortable WHERE type_id=3 AND top_y < 827 ORDER BY top_y ASC";
$result = $conn2->query($sql);

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    echo "データが見つかりませんでした。";
    exit;
}


$filteredData = [];
for ($i = 0; $i < count($data); $i++) {
    if ($data[$i]['top_y'] >= 55 && $data[$i]['top_y'] <= 60) {
        $startTopY = 55;
    } else {
        $startTopY = $data[$i]['top_y'];
    }

    // 最後の要素の場合、endTopY を top_y に 142 を足した値に設定
    if ($i + 1 < count($data)) {
        $endTopY = $data[$i + 1]['top_y'];
       
    } else {
        $endTopY = $data[$i]['top_y'] + 142;  // 最後の要素の場合は top_y に 142 を足した値
    }

    $keyName = mb_convert_kana(str_replace([" ", "\n", "\r", "\t"], "", $data[$i]['name']), "KV", "UTF-8");

    $nameSql = "SELECT name FROM sortable WHERE top_y >= ? AND top_y < ? AND left_x BETWEEN 1 AND 1390 AND type_id != 3 AND type_id IN (5,12)";
    $stmt = $conn2->prepare($nameSql);
    $stmt->bind_param("ii", $startTopY, $endTopY);
    $stmt->execute();
    $nameResult = $stmt->get_result();

    $nameArray = [];
    while ($nameRow = $nameResult->fetch_assoc()) {
        $nameArray[] = $nameRow['name'];
    }
    $stmt->close();

    $filteredData[$keyName] = $nameArray;
}

//取得したキーを確認
//var_dump($keyName);

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ja">
    <meta name="description" content="社内用">
    <meta name="robots" content="index, follow">
    <title>重機車両点検入力画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/inspection.css">

</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="container mt-5">

            <div class="row">
                <h2>重機車両点検</h2>
                <form action="submit_inspection.php" method="POST" onsubmit="return validateForm()">
                    <input type="hidden" name="inspection_type_id" value="<?php echo $inspection_type_id; ?>">

                    <div class="col-md-4 mb-2">
                        <label for="date" class="form-label">点検日</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="checker" class="form-label">点検者</label>
                        <select id="checker" name="checker_id" class="form-select" required>
                            <option value="" disabled selected><span style="color:red;">選択してください</span></option>
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
                            <option value="" selected disabled><span style="color:red;">選択してください</span></option>
                            <?php while ($row = $genbaResult->fetch_assoc()): ?>
                                <option value="<?php echo intval($row['genba_id']); ?>">
                                    <?php echo htmlspecialchars($row['genba_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div id="inspectionItems"></div>
                </form>

            </div>

        </div>
    </main>
    <?php include 'footer.php'; ?>

    <script>
        const data = <?php echo json_encode($filteredData); ?>;
        const inspectedItems = <?php echo json_encode($inspectedItems); ?>;

        //デバッグコード：inspectedItemsをコンソールに出力
        console.log("inspectedItems from JS:", inspectedItems);


        document.getElementById('genbaSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const selectedGenba = selectedOption.text;
            const normalizedSelectedGenba = selectedGenba.replace(/\s+/g, '').normalize('NFKC').trim();
            const inspectionItemsDiv = document.getElementById('inspectionItems');
            inspectionItemsDiv.innerHTML = '';

            // デバッグ用コード
            console.log('Selected Genba:', selectedGenba);
            console.log('Normalized Selected Genba:', normalizedSelectedGenba);
            console.log('Data:', data);
            console.log('Data for selected genba:', data[normalizedSelectedGenba]);

            if (!normalizedSelectedGenba || !data[normalizedSelectedGenba]) {
                console.log('No data found for this genba');
                return;
            }

            let otherButton;

            data[normalizedSelectedGenba].forEach(item => {
                const button = document.createElement('button');
                let itemKey = `${selectedOption.value}-${item}`;
                itemKey = itemKey.replace(/\s+/g, '').normalize('NFKC').trim();

                button.className = 'btn m-1';
                button.innerText = item;
                button.type = 'button';
                const isInspected = inspectedItems[itemKey] !== undefined;

                // デバッグ用コード
                console.log('Creating button for:');
                console.log('- Item:', item);
                console.log('- ItemKey:', itemKey); 
                console.log('- IsInspected:', isInspected);
                console.log('- InspectedItems[ItemKey]:', inspectedItems[itemKey]);

                if (isInspected) {
                    button.classList.add('btn-inspected');
                    console.log('Added btn-inspected class');
                } else {
                    button.classList.add('btn-historical');
                    console.log('Added btn-historical class');
                }

                if (!isInspected) {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        const currentDate = new Date().toISOString().split('T')[0];

                        fetch('check_car_item.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    date: currentDate,
                                    genba: normalizedSelectedGenba,
                                    item: item
                                })
                            })
                            .then(response => response.json())
                            .then(result => {
                                console.log('API Response:', result);
                                if (result.exists) {
                                    alert(`${currentDate}の${normalizedSelectedGenba}の${item}点検は既に登録されています。`);
                                } else {
                                    displayInspectionForm(item);
                                }
                            })
                            .catch(error => {
                                console.error('API Error:', error);
                                displayInspectionForm(item);
                            });
                    });
                }

                inspectionItemsDiv.appendChild(button);
            });

            otherButton = document.createElement('button');
            otherButton.className = 'btn m-1 btn-historical';
            otherButton.innerText = 'その他';
            otherButton.type = 'button';
            otherButton.addEventListener('click', () => {
                displayInspectionForm('');
            });
            inspectionItemsDiv.appendChild(otherButton);
            console.log('Other button added');
        });

        // フォームを表示する関数
        function displayInspectionForm(itemName) {
            const formContainer = document.getElementById('inspectionItems');
            const isInitialEmpty = itemName === ''; // 引数が空かどうかをチェック
            formContainer.innerHTML = `
            <div class="col-md-4 mb-2">
                <label for="target" class="form-label">点検対象</label>
                <select id="target" name="inspection_item_name" class="form-select" required>
                ${isInitialEmpty ? '<option value="" selected disabled style="color:red;">選択してください</option>' : ''}
                    <?php while ($row = $targetResult->fetch_assoc()) { ?>
                        <option value="<?php echo htmlspecialchars($row['name']); ?>" >
                            <?php echo sanitizeInput($row['name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <input type="hidden" name="inspection_type_id" value="<?php echo $inspection_type_id; ?>">
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
            <input type="hidden" name="source" value="get_staffing">
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