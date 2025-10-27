<?php
// エラーレポート設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必要なファイルのインクルード
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';


// データベース接続
$conn = connectDB();

// inspection_type_id を URL パラメータから取得、なければデフォルト値 18 を使用
$inspection_type_id = isset($_GET['inspection_type_id']) ? intval($_GET['inspection_type_id']) : 18;
// checker_id を URL パラメータから取得
$selected_checker_id = isset($_GET['checker_id']) ? intval($_GET['checker_id']) : null;
// genba_id を URL パラメータから取得
$selected_genba_id = isset($_GET['genba_id']) ? intval($_GET['genba_id']) : null;


// 点検済みデータ取得（例：当日のデータ）
$date = date('Y-m-d');
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

// `inspection_type` に基づいてアイテムを取得する SQL クエリ
$itemsSql = "SELECT * FROM inspection_items WHERE inspection_type_id = ?";
$stmt = $conn->prepare($itemsSql);
$stmt->bind_param('i', $inspection_type_id); // inspection_type_id をバインド
$stmt->execute();
$itemsResult = $stmt->get_result();
if (!$itemsResult) {
    die("Query failed: (" . $conn->errno . ") " . $conn->error);
}


$filteredData = getFilteredData($conn);

if ($filteredData === null) {
    exit; // getFilteredData内でエラーメッセージ出力済みのため、ここではexitのみ
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ja">
    <meta name="description" content="社内用">
    <meta name="robots" content="index, follow">
    <title>重機等点検入力画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/inspection.css">

</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="container mt-5">

            <div class="row">
                <h2>重機ローラー点検</h2>
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
                                $isSelected = ($selected_checker_id !== null && intval($row['checker_id']) === $selected_checker_id) ? 'selected' : '';
                                if (empty($isSelected) && isset($displayName) && $row['checker_name'] === $displayName) {
                                    $isSelected = 'selected';
                                }
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
                                <?php if (isset($filteredData[$row['genba_name']])): ?>
                                    <option value="<?php echo intval($row['genba_id']); ?>" <?php echo ($selected_genba_id !== null && intval($row['genba_id']) === $selected_genba_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['genba_name']); ?>
                                    </option>
                                <?php endif; ?>
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

        document.getElementById('genbaSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const selectedGenba = selectedOption.text;
            const normalizedSelectedGenba = selectedGenba.replace(/\s+/g, '').normalize('NFKC').trim();
            const inspectionItemsDiv = document.getElementById('inspectionItems');
            inspectionItemsDiv.innerHTML = '';

            if (!normalizedSelectedGenba || !data[normalizedSelectedGenba]) {
                return;
            }

            let otherButton;

            if (data[normalizedSelectedGenba]) {
                data[normalizedSelectedGenba].forEach(item => {
                    const button = document.createElement('button');
                    let itemKey = `${selectedOption.value}-${item}`;
                    let inspectionTypeIdForButton = (item === 'コンバインドローラー') ? 10 : 18;
                    button.dataset.inspectionTypeId = inspectionTypeIdForButton;

                    button.className = 'btn m-1';
                    button.innerText = item;
                    button.type = 'button';
                    const isInspected = inspectedItems[itemKey] !== undefined;

                    if (isInspected) {
                        button.classList.add('btn-inspected');
                    } else {
                        button.classList.add('btn-historical');
                    }

                    if (!isInspected) {
                        button.addEventListener('click', (event) => {
                            event.preventDefault();
                            const buttonElement = event.target;
                            const inspectionTypeIdFromButton = buttonElement.dataset.inspectionTypeId;
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
                                    if (result.exists) {
                                        alert(`${currentDate}の${normalizedSelectedGenba}の${item}点検は既に登録されています。`);
                                    } else {
                                        displayInspectionForm(item, inspectionTypeIdFromButton);
                                    }
                                })
                                .catch(error => {
                                    displayInspectionForm(item, inspectionTypeIdFromButton);
                                });
                        });
                    }

                    inspectionItemsDiv.appendChild(button);
                });
            }

            otherButton = document.createElement('button');
            otherButton.className = 'btn m-1 btn-historical';
            otherButton.innerText = 'その他';
            otherButton.type = 'button';
            otherButton.addEventListener('click', () => {
                displayInspectionForm('', 18);
            });
            inspectionItemsDiv.appendChild(otherButton);
        });

        // 新しいフォーム表示関数 (AJAX使用)
        function sanitizeInput(str) {
            const tempElement = document.createElement('div');
            tempElement.textContent = str;
            return tempElement.innerHTML;
        }

        async function displayInspectionForm(itemName, inspectionTypeIdFromButton) {
            const formContainer = document.getElementById('inspectionItems');
            const isInitialEmpty = itemName === '';

            try {
                const response = await fetch(`./get_inspection_form_data.php?inspection_type_id=${inspectionTypeIdFromButton}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();

                let targetOptionsHTML = isInitialEmpty ? '<option value="" selected disabled style="color:red;">選択してください</option>' : '';
                data.targets.forEach(target => {
                    targetOptionsHTML += `<option value="${sanitizeInput(target.name)}">${sanitizeInput(target.name)}</option>`;
                });

                let itemsHTML = '';
                data.items.forEach(item => {
                    itemsHTML += `
                    <div class="mb-3">
                        <label class="form-label">
                            ${item.sub ? `<p class="text-secondary">${sanitizeInput(item.sub)}</p>` : ''}
                            ${sanitizeInput(item.item_name)}
                        </label>
                        <div class="d-flex justify-content-end">
                            <div class="btn-group" role="group" aria-label="${sanitizeInput(item.item_name)}">
                                <input type="radio" id="item_${parseInt(item.item_id)}-1" name="item_${parseInt(item.item_id)}" value="〇" required class="btn-check" checked>
                                <label for="item_${parseInt(item.item_id)}-1" class="btn btn-sm btn-outline-warning rounded">〇</label>
                                <input type="radio" id="item_${parseInt(item.item_id)}-2" name="item_${parseInt(item.item_id)}" value="×" class="btn-check">
                                <label for="item_${parseInt(item.item_id)}-2" class="btn btn-sm btn-outline-warning rounded">×</label>
                                <input type="radio" id="item_${parseInt(item.item_id)}-3" name="item_${parseInt(item.item_id)}" value="ー" class="btn-check">
                                <label for="item_${parseInt(item.item_id)}-3" class="btn btn-sm btn-outline-warning rounded">ー</label>
                            </div>
                        </div>
                    </div>
                    `;
                });

                formContainer.innerHTML = `
                <div class="col-md-4 mb-2">
                    <label for="target" class="form-label">点検対象</label>
                    <select id="target" name="inspection_item_name" class="form-select" required>
                        ${targetOptionsHTML}
                    </select>
                </div>
                <input type="hidden" name="inspection_type_id" value="${inspectionTypeIdFromButton}">
                <div class="col-md-4 mb-2">
                    ${itemsHTML}
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
                } else if (itemName) {
                    const newOption = new Option(itemName, itemName, false, true);
                    selectElement.add(newOption);
                }

            } catch (error) {
                // フォームデータの取得エラー表示
                formContainer.innerHTML = `<p class="text-danger">フォームデータの取得に失敗しました。</p>`;
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const genbaId = urlParams.get('genba_id');
            const inspectionTypeId = urlParams.get('inspection_type_id');
            const checkerId = urlParams.get('checker_id');

            if (genbaId) {
                const genbaSelect = document.getElementById('genbaSelect');
                if (genbaSelect) {
                    const matchingOption = Array.from(genbaSelect.options).find(option =>
                        option.value === genbaId
                    );

                    if (matchingOption) {
                        genbaSelect.value = genbaId;
                        genbaSelect.dispatchEvent(new Event('change'));
                    }
                }
            }

            if (inspectionTypeId) {
                const genbaSelect = document.getElementById('genbaSelect');
                if (genbaSelect) {
                    genbaSelect.dispatchEvent(new Event('change'));
                }
            }

            if (checkerId) {
                const checkerSelect = document.getElementById('checker');
                if (checkerSelect) {
                    checkerSelect.value = checkerId;
                }
            }
        });

        function validateForm() {
            const inspectionItemName = document.getElementById('target').value.trim();
            const comments = document.getElementById('comments').value.trim();
            if (inspectionItemName === "") {
                alert("点検対象を選択してください。");
                return false;
            }
            document.getElementById('target').value = inspectionItemName;
            document.getElementById('comments').value = comments;
            return true;
        }
    </script>

    <script src="./js/common.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>