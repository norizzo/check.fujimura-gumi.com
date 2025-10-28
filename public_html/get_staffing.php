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
// date を URL パラメータから取得、なければ当日
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');


// 点検済みデータ取得（smart_assignmentsから取得）
$inspectedSql = "
    SELECT assignment_id, genba_id, target_name_id
    FROM smart_assignments
    WHERE assignment_date = ?
    AND inspection_completed = 1
";
$stmt = $conn->prepare($inspectedSql);
$stmt->bind_param('s', $date);
$stmt->execute();
$inspectedResult = $stmt->get_result();

$inspectedItems = [];
while ($row = $inspectedResult->fetch_assoc()) {
    // target_name_idベースのキーで点検完了を管理
    $key = $row['genba_id'] . '-' . $row['target_name_id'];
    $inspectedItems[$key] = true;
}
$stmt->close();
// inspectedItemsの中身をコンソールに出力
// echo "<script>console.log('inspectedItems (smart_assignments):', " . json_encode($inspectedItems) . ");</script>";

// smart_assignmentsから選択された日付の重機配置データを取得
$filteredData = getAssignmentsForInspection($conn, $date);

if ($filteredData === null || empty($filteredData)) {
    error_log("指定日付 {$date} の配置データが見つかりませんでした");
    // データがない場合は空配列として処理を継続
    $filteredData = [];
}

// smart_assignmentsに存在するgenba_idを取得
$filteredGenbaIds = array_keys($filteredData);

// genba_masterから現場を取得（finished=0 OR smart_assignmentsに存在する現場）
if (!empty($filteredGenbaIds)) {
    $genbaIdList = implode(',', $filteredGenbaIds);
    $genbaSql = "SELECT genba_id, genba_name FROM genba_master WHERE finished = 0 OR genba_id IN ($genbaIdList) ORDER BY genba_id ASC";
} else {
    $genbaSql = "SELECT genba_id, genba_name FROM genba_master WHERE finished = 0 ORDER BY genba_id ASC";
}

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

// $filteredDataの中身をコンソールに出力（デバッグ用）
// echo "<script>console.log('=== デバッグ情報 ===');</script>";
// echo "<script>console.log('選択された日付: " . $date . "');</script>";
// echo "<script>console.log('filteredData（smart_assignmentsから取得）:', " . json_encode($filteredData, JSON_UNESCAPED_UNICODE) . ");</script>";
// echo "<script>console.log('filteredDataのキー（genba_id）一覧:', " . json_encode(array_keys($filteredData), JSON_UNESCAPED_UNICODE) . ");</script>";
// echo "<script>console.log('genba_masterから取得される現場は以下でチェック↓');</script>";

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo $date; ?>" required onchange="reloadWithDate(this.value)">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="checker" class="form-label">点検者</label>
                        <select id="checker" name="checker_id" class="form-select" required>
                            <option value="" disabled selected><span style="color:red;">選択してください</span></option>
                            <?php while ($row = $checkerResult->fetch_assoc()) {
                                // URLパラメータに checker_id が存在し、現在のチェッカーIDと一致する場合、または $displayName が存在し、checker_name と一致する場合に `selected` を追加
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
                                <?php
                                // genba_idベースで存在チェック
                                $genbaId = intval($row['genba_id']);
                                $exists = isset($filteredData[$genbaId]);

                                // デバッグ出力を追加
                                // echo "<script>console.log('genba_id={$genbaId}, 現場: " . addslashes($row['genba_name']) . " → 存在: " . ($exists ? 'true' : 'false') . "');</script>";

                                if ($exists) {
                                    // echo "<script>console.log('  → filteredData[{$genbaId}]:', " . json_encode($filteredData[$genbaId], JSON_UNESCAPED_UNICODE) . ");</script>";
                                    // echo "<script>console.log('  → ドロップダウンに追加: genba_id={$genbaId}, name=" . addslashes($row['genba_name']) . "');</script>";
                                }
                                ?>
                                <?php if ($exists): ?>
                                    <option value="<?php echo $genbaId; ?>" <?php echo ($selected_genba_id !== null && $genbaId === $selected_genba_id) ? 'selected' : ''; ?>>
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
        // 日付変更時にページをリロード
        function reloadWithDate(selectedDate) {
            const url = new URL(window.location.href);
            url.searchParams.set('date', selectedDate);
            window.location.href = url.toString();
        }

        const data = <?php echo json_encode($filteredData); ?>;
        const inspectedItems = <?php echo json_encode($inspectedItems); ?>;

        //デバッグコード：inspectedItemsをコンソールに出力
        // console.log("inspectedItems from JS:", inspectedItems);


        document.getElementById('genbaSelect').addEventListener('change', function() {
            // 現場選択セレクトボックスのchangeイベントリスナーを設定
            const selectedOption = this.options[this.selectedIndex];
            // 選択されたオプション要素を取得
            const selectedGenbaId = selectedOption.value;
            // 選択されたgenba_idを取得
            const selectedGenba = selectedOption.text;
            // 選択された現場名（テキスト）を取得
            const normalizedSelectedGenba = selectedGenba.replace(/\s+/g, '').normalize('NFKC').trim();
            // 現場名を正規化（APIで使用）
            const inspectionItemsDiv = document.getElementById('inspectionItems');
            // 点検項目を表示するdiv要素を取得
            inspectionItemsDiv.innerHTML = '';
            // 点検項目表示divの中身を空にする（以前の内容をクリア）

            // デバッグ用コード
            console.log('選択されたgenba_id:', selectedGenbaId);
            console.log('選択された現場名:', selectedGenba);
            console.log('正規化された現場名:', normalizedSelectedGenba);
            console.log('Data:', data);
            console.log('選択された現場のデータ:', data[selectedGenbaId]);

            if (!selectedGenbaId || !data[selectedGenbaId]) {
                console.log('この現場のデータが見つかりません');
                return;
            }

            let otherButton;
            // 「その他」ボタンを格納する変数を宣言

            const genbaData = data[selectedGenbaId];
            if (genbaData && genbaData.machines) {
                genbaData.machines.forEach(machineObj => {
                    // 選択された現場の点検項目データ配列をループ処理
                    const item = machineObj.name; // 重機名
                    const targetNameId = machineObj.target_name_id; // target_name_id

                    const button = document.createElement('button');
                    // ボタン要素を生成
                    let itemKey = `${selectedOption.value}-${targetNameId}`;
                    // itemKeyを生成 (genba_id-target_name_id の形式に変更)

                    let inspectionTypeIdForButton = (item === 'コンバインドローラー') ? 10 : 18; // Determine inspection_type_id
                    button.dataset.inspectionTypeId = inspectionTypeIdForButton; // Set data attribute
                    button.dataset.targetNameId = targetNameId; // target_name_idも保存


                    button.className = 'btn m-1';
                    // ボタンのCSSクラスを設定 (Bootstrapのボタンとマージン)
                    button.innerText = item;
                    // ボタンのテキストを点検項目名に設定
                    button.type = 'button';
                    // ボタンのタイプをbuttonに設定
                    const isInspected = inspectedItems[itemKey] !== undefined;
                    // target_name_idベースで点検済みチェック
                    // inspectedItemsオブジェクトにitemKeyが存在するか確認 (点検済みかどうかを判定)

                    // デバッグ用コード
                    // console.log('Creating button for:');
                    // console.log('- Item:', item);
                    // console.log('- ItemKey:', itemKey);
                    // console.log('- IsInspected:', isInspected);
                    // console.log('- InspectedItems[ItemKey]:', inspectedItems[itemKey]);
                    // ボタン生成に関する情報をコンソールに出力

                    // デバッグ: 比較する値をコンソールに出力
                    // console.log('itemKey:', itemKey);
                    // console.log('inspectedItems[itemKey]:', inspectedItems[itemKey]);
                    // console.log('Condition: inspectedItems [itemKey] !== undefined is', inspectedItems[itemKey] !== undefined);


                    if (isInspected) {
                        // 点検済みの場合
                        button.classList.add('btn-inspected');
                        // ボタンに点検済みのCSSクラスを追加
                        // console.log('Added btn-inspected class');
                        // クラス追加をコンソールに出力
                    } else {
                        // 未点検の場合
                        button.classList.add('btn-historical');
                        // ボタンに未点検（過去データあり）のCSSクラスを追加
                        // console.log('Added btn-historical class');
                        // クラス追加をコンソールに出力
                    }

                    if (!isInspected) {
                        // 未点検の場合のみクリックイベントリスナーを設定
                        button.addEventListener('click', (event) => {
                            event.preventDefault();
                            const buttonElement = event.target;
                            const inspectionTypeIdFromButton = buttonElement.dataset.inspectionTypeId;
                            const targetNameIdFromButton = buttonElement.dataset.targetNameId;

                            // smart_assignmentsで点検済みチェック済みのため、直接フォーム表示
                            displayInspectionForm(item, inspectionTypeIdFromButton, targetNameIdFromButton);
                        });
                    }

                    inspectionItemsDiv.appendChild(button);
                    // 生成したボタンを点検項目表示divに追加
                });
            }


            otherButton = document.createElement('button');
            otherButton.className = 'btn m-1 btn-historical';
            otherButton.innerText = 'その他';
            otherButton.type = 'button';
            otherButton.addEventListener('click', () => {
                displayInspectionForm('', 18); // default inspection_type_id for 'その他'
            });
            inspectionItemsDiv.appendChild(otherButton);
            // console.log('Other button added');
        });


        // 新しいフォーム表示関数 (AJAX使用)
        function sanitizeInput(str) {
            const tempElement = document.createElement('div');
            tempElement.textContent = str;
            return tempElement.innerHTML;
        }

        async function displayInspectionForm(itemName, inspectionTypeIdFromButton, targetNameId = null) {
            // console.log('displayInspectionForm called with itemName:', itemName, 'inspectionTypeIdFromButton:', inspectionTypeIdFromButton);

            const formContainer = document.getElementById('inspectionItems');
            const isInitialEmpty = itemName === '';

            try {
                const response = await fetch(`./get_inspection_form_data.php?inspection_type_id=${inspectionTypeIdFromButton}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                // console.log('Fetched inspection form data:', data);

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
                ${targetNameId ? `<input type="hidden" name="target_name_id" value="${targetNameId}">` : ''}
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
                // console.log('formContainer.innerHTML set:', formContainer.innerHTML);

                const selectElement = document.getElementById('target');
                const options = Array.from(selectElement.options);
                const existingOption = options.find(option => option.value === itemName);

                if (existingOption) {
                    existingOption.selected = true;
                } else if (itemName) { // itemName が空でない場合のみ新しいオプションを追加
                    const newOption = new Option(itemName, itemName, false, true);
                    selectElement.add(newOption);
                }


            } catch (error) {
                // console.error('Error fetching inspection form data:', error);
                formContainer.innerHTML = `<p class="text-danger">フォームデータの取得に失敗しました。</p>`;
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const genbaId = urlParams.get('genba_id');
            const inspectionTypeId = urlParams.get('inspection_type_id');
            const checkerId = urlParams.get('checker_id'); // checker_id を取得

            // console.log('URLパラメータ:', { genbaId, inspectionTypeId, checkerId });

            if (genbaId) {
                // console.log('genbaId が存在します:', genbaId);
                const genbaSelect = document.getElementById('genbaSelect');
                // console.log('genbaSelect 要素:', genbaSelect);
                if (genbaSelect) {
                    const matchingOption = Array.from(genbaSelect.options).find(option =>
                        option.value === genbaId
                    );

                    if (matchingOption) {
                        // console.log('一致する現場IDが見つかりました:', genbaId);
                        // console.log('genbaSelect の現在の値 (設定前):', genbaSelect.value);
                        genbaSelect.value = genbaId;
                        // console.log('genbaSelect の現在の値 (設定後):', genbaSelect.value);
                        genbaSelect.dispatchEvent(new Event('change'));
                        // console.log('genbaSelect の change イベントを発火させました');
                    } else {
                        // console.error('一致する現場IDが見つかりませんでした:', genbaId);
                    }
                } else {
                    // console.error('genbaSelect 要素が見つかりませんでした');
                }
            } else {
                // console.log('genbaId は URL パラメータにありません');
            }

            // URLパラメータから inspectionTypeId を取得し、存在すればgenbaSelectのchangeイベントを発火させる
            if (inspectionTypeId) {
                // console.log('inspectionTypeId が存在します:', inspectionTypeId);
                const genbaSelect = document.getElementById('genbaSelect');
                if (genbaSelect) {
                    genbaSelect.dispatchEvent(new Event('change'));
                    // console.log('genbaSelect の change イベントを発火させました (inspectionTypeId により)');
                } else {
                    // console.error('genbaSelect 要素が見つかりませんでした (inspectionTypeId)');
                }

            } else {
                // console.log('inspectionTypeId は URL パラメータにありません');
            }

            // URLパラメータから checkerId を取得し、存在すれば点検者セレクトボックスの値を設定
            if (checkerId) {
                // console.log('checkerId が存在します:', checkerId);
                const checkerSelect = document.getElementById('checker');
                // console.log('checkerSelect 要素:', checkerSelect);
                if (checkerSelect) {
                    // console.log('checkerSelect の現在の値 (設定前):', checkerSelect.value);
                    checkerSelect.value = checkerId;
                    // console.log('checkerSelect の現在の値 (設定後):', checkerSelect.value);
                } else {
                    // console.error('checkerSelect 要素が見つかりませんでした');
                }
            } else {
                // console.log('checkerId は URL パラメータにありません');
            }
        });

        function validateForm() {
            // 点検対象をトリム
            const inspectionItemName = document.getElementById('target').value.trim();

            // コメントをトリム
            const comments = document.getElementById('comments').value.trim();
            // console.log("トリム後の点検対象:", inspectionItemName);
            // console.log("トリム後のコメント:", comments);
            // もし点検対象が空の場合、エラーメッセージを表示
            if (inspectionItemName === "") {
                alert("点検対象を選択してください。");
                return false;
            }

            // 必要に応じて、コメントの内容を処理することもできます
            if (comments === "") {
                // console.log("コメントが空です。必要に応じて処理を追加してください。");
            }

            // トリムしたデータをフォームに再設定して送信
            document.getElementById('target').value = inspectionItemName;
            document.getElementById('comments').value = comments;

            // フォームが送信される
            return true;
        }
        // 📡 ポーリング: 配置データ更新チェック（10秒間隔）
        let lastUpdateTime = null;
        // let currentGenbaId = null;
        let currentDate = '<?php echo $date; ?>';
        let pollingInterval = null;

        // ページ読み込み時に即座にポーリング開始
        console.log('📡 ページ読み込み時ポーリング開始: date=' + currentDate);
        checkDataUpdate(); // 初回チェック
        pollingInterval = setInterval(checkDataUpdate, 10000); // 10秒ごと

        

        function checkDataUpdate() {


            fetch('check_data_update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'date=' + encodeURIComponent(currentDate)
                })
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        if (lastUpdateTime && response.last_update !== lastUpdateTime) {
                            // データが更新された
                            console.log('⚠️ 配置データ更新検知: ' + lastUpdateTime + ' → ' + response.last_update);

                            alert('配置データが更新されました。ページを再読み込みします。');
                            location.reload();
                        }
                        lastUpdateTime = response.last_update;
                        console.log('✅ データチェック完了: ' + response.last_update + ' (' + response.count + '件)');
                    }
                })
                .catch(error => {
                    console.error('❌ ポーリングエラー:', error);
                });
        }

        // ページ離脱時にポーリング停止
        window.addEventListener('beforeunload', function() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
    </script>

    <script src="./js/common.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>