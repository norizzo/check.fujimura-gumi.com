<?php

use Kanagama\Holidays\Holidays;

/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Error details: " . print_r(error_get_last(), true)); */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';

$conn = connectDB();

// デフォルトは本日の日付
$selected_date = date('Y-m-d');

$filteredData = getFilteredData($conn, $selected_date);

if ($filteredData === null) {
    exit;
}

// JavaScriptに渡す前にJSONエンコード
$filteredDataJson = json_encode($filteredData, JSON_UNESCAPED_UNICODE);
//現場id取得
$genbaSql = "SELECT genba_id, genba_name FROM genba_master WHERE finished = 0 ORDER BY genba_id ASC";
$genbaResult = $conn->query($genbaSql);
if (!$genbaResult) {
    die("Genba query failed: (" . $conn->errno . ") " . $conn->error);
}

// 点検種類とカテゴリを取得
$sql = "SELECT * FROM inspection_types WHERE category NOT IN ('重機・車両', '発電機') ORDER BY category";
$result = $conn->query($sql);

// カテゴリごとにグループ化
$categories = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[$row['category']][] = $row;
    }
}
// 選択された日付の点検履歴を取得（URLパラメータまたは本日）
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$sql = "SELECT DISTINCT i.inspection_type_id, i.genba_id 
        FROM inspections i 
        WHERE DATE(i.date) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$todayInspectionResult = $stmt->get_result();

// 全期間の点検履歴を取得
$sql = "SELECT DISTINCT inspection_type_id, genba_id 
        FROM inspections";
$allInspectionResult = $conn->query($sql);

// 点検履歴を配列に格納
$selectedDateInspectedItems = [];  // 選択された日付の点検済みアイテム
$historicalInspectedItems = [];

while ($row = $todayInspectionResult->fetch_assoc()) {
    $key = $row['genba_id'] . '-' . $row['inspection_type_id'];
    $selectedDateInspectedItems[$key] = true;
}

while ($row = $allInspectionResult->fetch_assoc()) {
    $key = $row['genba_id'] . '-' . $row['inspection_type_id'];
    $historicalInspectedItems[$key] = true;
}

// JavaScript用にデータを準備
$selectedDateInspectedItemsJson = json_encode($selectedDateInspectedItems);
$historicalInspectedItemsJson = json_encode($historicalInspectedItems);
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../css/inspection.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- <style>
        body,
        button {
            font-size: initial !important;
            padding: initial !important;
        }
    </style> -->
</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="container mt-5">
            <div class="row">
                <div class="col-12">
                    <h2>点検</h2>
                </div>
                <!-- 日付選択 -->
                <div class="col-1212 mb-3">
                    <label for="dateSelect" class="form-label">日付を選択してください</label>
                    <input type="date" id="dateSelect" class="form-control w-auto" value="<?php echo date('Y-m-d'); ?>" onchange="changeDate(this)">
                </div>

                <!-- 現場選択 -->
                <div class="col-1212 mb-3">
                    <label for="genbaSelect" class="form-label">現場名を選択してください</label>
                    <select id="genbaSelect" name="genba_id" class="form-select w-auto" onchange="changeColorGenba(this)" required>
                        <option value="" selected disabled><span style="color:red;">選択してください</span></option>
                        <?php while ($row = $genbaResult->fetch_assoc()): ?>
                            <?php
                            $genbaId = intval($row['genba_id']);
                            // genba_idベースで存在チェック
                            $exists = isset($filteredData[$genbaId]);
                            ?>
                            <?php if ($exists): ?>
                                <option value="<?php echo intval($row['genba_id']); ?>">
                                    <?php echo htmlspecialchars($row['genba_name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </select>

                    <!-- <label for="genbaSelect" class="form-label">現場を選択:</label>
                    <select class="form-select" id="genbaSelect" onchange="changeColorGenba(this)"">
                        <option value="">現場を選択してください</option>
                        <?php foreach ($filteredData as $genbaName => $genbaData): ?>
                                    <option value="<?php echo htmlspecialchars($genbaData['genba_id']); ?>">
                                        <?php echo htmlspecialchars($genbaName); ?>
                                    </option>
                        <?php endforeach; ?>
                    </select> -->
                </div>
            </div>

            <!-- 点検項目 -->
            <div id="inspectionItems" class="col-12 col-md-6 mb-3">
                <?php foreach ($categories as $category => $items): ?>
                    <?php if (in_array($category, ['場所', '機械', '道具'])): ?>
                        <div class="category-group visible mb-4" data-category="<?= htmlspecialchars($category) ?>">
                            <h3 class="mb-3 text-secondary"><?= htmlspecialchars($category) ?></h3>
                            <div class="d-flex flex-wrap" role="group">
                                <?php foreach ($items as $item): ?>
                                    <button
                                        type="button"
                                        class="btn btn-category btn-primary open-modal-button m-2 px-3 py-2"
                                        data-inspection-id="<?= intval($item['type_id']) ?>">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- モーダル -->
            <div class="modal fade" id="inspectionModal" tabindex="-1" aria-labelledby="inspectionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="inspectionModalLabel">点検フォーム</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                        </div>
                        <div class="modal-body">
                            <iframe id="inspectionFormFrame" src="" width="100%" height="700px" frameborder="0"></iframe>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 選択された日付の点検済みアイテム（初期値：PHP側で取得）
        let selectedDateInspectedItems = <?php echo json_encode($selectedDateInspectedItems); ?>;
        const historicalInspectedItems = <?php echo json_encode($historicalInspectedItems); ?>;
        // PHPから渡された$filteredDataをJavaScriptで利用できるようにする
        const filteredData = <?php echo $filteredDataJson; ?>;

        // コンソールに$filteredDataの中身を表示
        console.log('filteredData:', filteredData);
        // 日付変更時に現場リストを更新
        function changeDate(dateInput) {
            const selectedDate = dateInput.value;

            fetch(`get_genba_list.php?date=${selectedDate}`)
                .then(response => response.json())
                .then(data => {
                    const genbaSelect = document.getElementById('genbaSelect');
                    // 既存のオプションをクリア（最初のプレースホルダー以外）
                    genbaSelect.innerHTML = '<option value="" selected disabled><span style="color:red;">選択してください</span></option>';

                    // 新しい現場リストを追加
                    data.forEach(genba => {
                        const option = document.createElement('option');
                        option.value = genba.genba_id;
                        option.textContent = genba.genba_name;
                        genbaSelect.appendChild(option);
                    });

                    // ボタンの状態をリセット
                    document.querySelectorAll('.open-modal-button').forEach(button => {
                        button.classList.remove('btn-inspected', 'btn-historical', 'btn-new');
                        button.disabled = false;
                    });
                })
                .catch(error => {
                    console.error('現場リストの取得に失敗しました:', error);
                });
        }

        document.getElementById('genbaSelect').addEventListener('change', updateButtons);

        function updateButtons() {
            const selectedGenbaId = document.getElementById('genbaSelect').value;
            console.log(selectedGenbaId);
            // 現場が選択されていない場合、処理をスキップ
            if (!selectedGenbaId) {
                return;
            }

            // 選択された現場IDと日付を元に、get_inspection_status.phpから点検状況を取得
            const selectedDate = document.getElementById('dateSelect').value;
            fetch(`get_inspection_status.php?genba_id=${selectedGenbaId}&date=${selectedDate}`)
                .then(response => response.json())
                .then(data => {
                    // console.log('取得したデータ:', data);
                    // selectedDateInspectedItems を取得したデータで更新
                    selectedDateInspectedItems = data;
                    // 全ての .open-modal-button 要素に対して処理を行う
                    document.querySelectorAll('.open-modal-button').forEach(button => {
                        const inspectionId = button.getAttribute('data-inspection-id');
                        const key = `${selectedGenbaId}-${inspectionId}`;

                        // 既存のクラスを削除して、ボタンの状態をリセット
                        button.classList.remove('btn-inspected', 'btn-historical', 'btn-new');
                        button.disabled = false;

                        // 選択された日付で点検済みの場合
                        if (selectedDateInspectedItems[key]) {
                            button.classList.add('btn-inspected');
                            button.disabled = true;
                            // 過去に点検済みの場合
                        } else if (historicalInspectedItems[key]) {
                            button.classList.add('btn-historical');
                            // 未点検の場合
                        } else {
                            button.classList.add('btn-new');
                        }
                    });
                })
                .catch(error => {
                    // console.error('点検状況の取得に失敗しました:', error);
                    // エラー処理を追加 (例: エラーメッセージを表示するなど)
                });
        }

        document.querySelectorAll('.open-modal-button').forEach(button => {
            button.addEventListener('click', function(event) {
                const selectedGenbaId = document.getElementById('genbaSelect').value;
                if (!selectedGenbaId) {
                    alert('現場を選択してください');
                    return;
                }
                const inspectionId = this.dataset.inspectionId;
                const selectedGenbaName = document.getElementById('genbaSelect').options[document.getElementById('genbaSelect').selectedIndex].text;

                const key = `${selectedGenbaId}-${inspectionId}`;
                if (selectedDateInspectedItems[key]) {
                    alert('選択された日付の点検は既に完了しています。');
                    return;
                }

                const iframe = document.getElementById('inspectionFormFrame');
                const url = `inspection_m_form.php?inspection_type=${inspectionId}&genba_id=${selectedGenbaId}&genba_name=${encodeURIComponent(selectedGenbaName)}`;
                console.log('Generated URL:', url); // URLをコンソールに出力
                iframe.src = url;

                const modal = new bootstrap.Modal(document.getElementById('inspectionModal'));
                modal.show();
            });
        });

        // iframe からのメッセージを受け取る
        window.addEventListener('message', function(event) {
            if (event.data && event.data.closeModal) {
                // モーダルを閉じる
                const modal = bootstrap.Modal.getInstance(document.getElementById('inspectionModal'));
                if (modal) {
                    modal.hide();
                } else {
                    console.error("モーダルが見つかりません。");
                }
                // モーダル閉じ後、ボタンの状態を更新
                updateButtons();
            }
        });

        // iframeのエラーをキャッチ
        const iframe = document.getElementById('inspectionFormFrame');
        iframe.onerror = function() {
            console.error('iframe loading error');
        };


        updateButtons(); // 初期表示時のボタン状態更新
    </script>
    <script src="./js/common.js"></script>

</html>