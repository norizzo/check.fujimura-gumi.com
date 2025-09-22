<?php

use Kanagama\Holidays\Holidays;

/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Error details: " . print_r(error_get_last(), true)); */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once 'config.php';
require_once 'functions.php';

$conn = connectDB();

// 現場の取得
$sql = "SELECT * FROM genba_master WHERE finished = 0 ORDER BY genba_name";
$genbaResult = $conn->query($sql);
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
// 本日の点検履歴を取得
$today = date('Y-m-d');
$sql = "SELECT DISTINCT i.inspection_type_id, i.genba_id 
        FROM inspections i 
        WHERE DATE(i.date) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $today);
$stmt->execute();
$todayInspectionResult = $stmt->get_result();

// 全期間の点検履歴を取得
$sql = "SELECT DISTINCT inspection_type_id, genba_id 
        FROM inspections";
$allInspectionResult = $conn->query($sql);

// 点検履歴を配列に格納
$todayInspectedItems = [];
$historicalInspectedItems = [];

while ($row = $todayInspectionResult->fetch_assoc()) {
    $key = $row['genba_id'] . '-' . $row['inspection_type_id'];
    $todayInspectedItems[$key] = true;
}

while ($row = $allInspectionResult->fetch_assoc()) {
    $key = $row['genba_id'] . '-' . $row['inspection_type_id'];
    $historicalInspectedItems[$key] = true;
}

// JavaScript用にデータを準備
$todayInspectedItemsJson = json_encode($todayInspectedItems);
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

                <!-- 現場選択 -->
                <div class="col-12 col-md-6 mb-3">
                    <label for="genbaSelect" class="form-label">現場を選択:</label>
                    <select class="form-select" id="genbaSelect" onchange="changeColorGenba(this)"">
                        <option value="">現場を選択してください</option>
                        <?php while ($genba = $genbaResult->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($genba['genba_id']) ?>">
                                <?= htmlspecialchars($genba['genba_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
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
        let todayInspectedItems = <?php echo json_encode($todayInspectedItems); ?>;
        const historicalInspectedItems = <?php echo json_encode($historicalInspectedItems); ?>;

        document.getElementById('genbaSelect').addEventListener('change', updateButtons);

        function updateButtons() {
            const selectedGenbaId = document.getElementById('genbaSelect').value;
            // 現場が選択されていない場合、処理をスキップ
            if (!selectedGenbaId) {
                return;
            }

            // 選択された現場IDと日付を元に、get_inspection_status.phpから点検状況を取得
            fetch(`get_inspection_status.php?genba_id=${selectedGenbaId}&date=<?php echo $today; ?>`)
                .then(response => response.json())
                .then(data => {
                    // console.log('取得したデータ:', data);
                    // todayInspectedItems を取得したデータで更新
                    todayInspectedItems = data;
                    // 全ての .open-modal-button 要素に対して処理を行う
                    document.querySelectorAll('.open-modal-button').forEach(button => {
                        const inspectionId = button.getAttribute('data-inspection-id');
                        const key = `${selectedGenbaId}-${inspectionId}`;

                        // 既存のクラスを削除して、ボタンの状態をリセット
                        button.classList.remove('btn-inspected', 'btn-historical', 'btn-new');
                        button.disabled = false;

                        // 本日点検済みの場合
                        if (todayInspectedItems[key]) {
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
                if (todayInspectedItems[key]) {
                    alert('本日の点検は既に完了しています。');
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

<?php closeDB($conn); ?>