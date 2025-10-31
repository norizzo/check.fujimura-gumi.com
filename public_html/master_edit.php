<?php
/* ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL); */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';

$aollwed_users = ['田中利憲', '本山塁', '杉本義夫', '小島聡明', '小山哲郎', '藤村英明', '山本一弘'];
$username = $_SESSION['display_name'] ?? '';

if (ob_get_level()) ob_end_flush();
$conn = connectDB();
if (!$conn) {
    error_log('Database connection failed in master_edit.php: ' . mysqli_connect_error()); // データベース接続失敗をログに残す
    throw new Exception('Database connection failed');
} else {
    error_log('Database connection successful in master_edit.php'); // データベース接続成功をログに残す
}


$masterTables = [
    'checker_master' => ['checker_name', 'checker_phonetic', 'hidden', 'checker_id', 'primary_key' => 'checker_id'],
    'genba_master' => ['genba_name', 'finished', 'genba_id', 'primary_key' => 'genba_id'],
    'inspection_items' => ['inspection_type_id', 'item_name', 'sub', 'sort', 'item_id', 'primary_key' => 'item_id'],
    'inspection_master' => ['i_m_id', 'name', 'category', 'primary_key' => 'i_m_id'],
    'inspection_types' => ['name', 'category', 'type_id', 'primary_key' => 'type_id'],
    'target_name' => ['category', 'name', 'id', 'primary_key' => 'id']
];

$selectedTable = isset($_GET['table']) ? $_GET['table'] : '';
$error_message = '';
$success_message = '';

// AJAXリクエストの処理
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    // POSTリクエストの処理（UPDATE/INSERT共通）
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log('AJAX POST request received in master_edit.php'); // POSTリクエスト受信をログに残す
        header('Content-Type: application/json');
        try {
            error_log('POST request data: ' . print_r($_POST, true));

            // POSTされたデータからテーブル名を取得
            $table = $_POST['table'];
            error_log('Table from POST: ' . $table);

            // 設定ファイルから主キー名を取得
            $primaryKey = $masterTables[$table]['primary_key'];
            error_log('Primary key for table ' . $table . ': ' . $primaryKey);

            // フォームから送信された主キーの値を取得
            $id = $_POST[$primaryKey] ?? null;
            error_log('Primary key value from POST: ' . $id);

            if (!array_key_exists($table, $masterTables)) {
                error_log('Invalid table name: ' . $table);
                throw new Exception('Invalid table name');
            }

            // データの準備（主キーと'table'を除外）
            $data = [];
            foreach ($_POST as $key => $value) {
                if ($key !== 'table' && $key !== $primaryKey && $key !== 'action') { // 'action' を除外
                    // target_nameテーブルのnameフィールドの場合、自動変換を適用
                    if ($table === 'target_name' && $key === 'name') {
                        // 英数字を半角、日本語（カナ）を全角に統一
                        $value = mb_convert_kana($value, 'asKV', 'UTF-8');
                    }
                    $data[$key] = $value;
                }
            }
            error_log('Data to process: ' . print_r($data, true));

            // アクションの種類をチェック (finished カラムのトグル更新かどうか)
            $action = $_POST['action'] ?? '';
            error_log('Action from POST: ' . $action);
            if ($action === 'toggleChange' && (
                    ($table === 'genba_master' && isset($_POST['finished'], $_POST['genba_id'])) ||
                    ($table === 'checker_master' && isset($_POST['hidden'], $_POST['checker_id'])) ||
                    ($table === 'target_name' && isset($_POST['hidden'], $_POST['id']))
                )) {
                // finished/hidden カラムのトグル更新処理
                $columnName = '';
                $recordIdName = '';
                $recordId = '';
                $columnValue = '';

                if ($table === 'genba_master') {
                    $columnName = 'finished';
                    $recordIdName = 'genba_id';
                    $recordId = $_POST['genba_id'];
                    $columnValue = $_POST['finished'];
                } else if ($table === 'checker_master' || $table === 'target_name') {
                    $columnName = 'hidden';
                    $recordIdName = ($table === 'checker_master') ? 'checker_id' : 'id';
                    $recordId = $_POST[$recordIdName];
                    $columnValue = $_POST['hidden'];
                }

                $updateSql = "UPDATE `$table` SET `$columnName` = ? WHERE `$recordIdName` = ?";
                error_log('Executing toggle SQL: ' . $updateSql); // SQLクエリをログに出力
                error_log('Toggle parameters - columnValue: ' . $columnValue . ', recordId: ' . $recordId); // パラメータをログに出力

                $stmt = $conn->prepare($updateSql); // prepare statement はここで定義する
                if (!$stmt) {
                    error_log('Prepare statement failed for toggle: ' . $conn->error);
                    throw new Exception('Failed to prepare update statement: ' . $conn->error);
                }
                error_log('Prepare statement successful for toggle');

                $stmt->bind_param('si', $columnValue, $recordId); // columnValue を string 's' , recordId を integer 'i' に変更
                error_log('Bind param successful for toggle');

                if (!$stmt->execute()) {
                    error_log('Execute statement failed for toggle: ' . $stmt->error);
                    throw new Exception('Failed to execute update statement: ' . $stmt->error);
                }
                error_log('Execute statement successful for toggle');

                $result = [
                    'success' => true,
                    'message' => '表示/非表示設定を更新しました。',
                    'affected_rows' => $stmt->affected_rows
                ];
                error_log('Toggle update result: ' . print_r($result, true));

            } else {

                // IDがある場合はUPDATE、ない場合はINSERT (既存の処理)
                if (!empty($id)) {
                    // UPDATE処理
                    error_log('Performing UPDATE operation');

                    $updateSql = "UPDATE `$table` SET ";
                    $updateValues = [];
                    $params = [];
                    $types = '';

                    foreach ($data as $key => $value) {
                        $updateValues[] = "`$key` = ?";
                        $params[] = $value;
                        $types .= 's';
                    }

                    $updateSql .= implode(', ', $updateValues) . " WHERE `$primaryKey` = ?";
                    $params[] = $id;
                    $types .= 'i';

                    error_log('Update SQL: ' . $updateSql);
                    error_log('Update Params: ' . print_r($params, true));

                    $stmt = $conn->prepare($updateSql);
                    if (!$stmt) {
                        error_log('Prepare statement failed for update: ' . $conn->error);
                        throw new Exception('Failed to prepare update statement: ' . $conn->error);
                    }
                    error_log('Prepare statement successful for update');

                    $stmt->bind_param($types, ...$params);
                    error_log('Bind param successful for update');

                    if (!$stmt->execute()) {
                        error_log('Execute statement failed for update: ' . $stmt->error);
                        throw new Exception('Failed to execute update statement: ' . $stmt->error);
                    }
                    error_log('Execute statement successful for update');

                    $result = [
                        'success' => true,
                        'message' => '更新が完了しました。',
                        'affected_rows' => $stmt->affected_rows
                    ];
                    error_log('Update operation result: ' . print_r($result, true));
                } else {
                    // INSERT処理
                    error_log('Performing INSERT operation');

                    // データが空の場合はエラー
                    if (empty($data)) {
                        error_log('No data to insert');
                        throw new Exception('No data to insert');
                    }

                    $insertSql = "INSERT INTO `$table` (" . implode(', ', array_keys($data)) . ") VALUES (" .
                        implode(', ', array_fill(0, count($data), '?')) . ")";

                    error_log('Insert SQL: ' . $insertSql);
                    error_log('Insert Data: ' . print_r($data, true));

                    $stmt = $conn->prepare($insertSql);
                    if (!$stmt) {
                        error_log('Prepare statement failed for insert: ' . $conn->error);
                        throw new Exception('Failed to prepare insert statement: ' . $conn->error);
                    }
                    error_log('Prepare statement successful for insert');

                    $types = str_repeat('s', count($data));
                    $stmt->bind_param($types, ...array_values($data));
                    error_log('Bind param successful for insert');

                    if (!$stmt->execute()) {
                        error_log('Execute statement failed for insert: ' . $stmt->error);
                        throw new Exception('Failed to execute insert statement: ' . $stmt->error);
                    }
                    error_log('Execute statement successful for insert');

                    $result = [
                        'success' => true,
                        'message' => '新規登録が完了しました。',
                        'affected_rows' => $stmt->affected_rows,
                        'last_insert_id' => $conn->insert_id
                    ];
                    error_log('Insert operation result: ' . print_r($result, true));
                }
            }

            error_log('Result before json_encode: ' . print_r($result, true)); // JSONエンコード直前に $result をログ出力
            // 結果を返す
            echo json_encode($result);
            if (isset($stmt)) $stmt->close(); // $stmt が定義されているか確認してから close() を呼び出す
            error_log('JSON response sent and statement closed');

        } catch (Exception $e) {
            error_log('Exception in AJAX POST handler: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    // GETリクエストの処理（テーブル部分の更新）
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['table'])) {
        error_log('AJAX GET request received for table update in master_edit.php'); // GETリクエスト受信をログに残す
        if (in_array($_GET['table'], array_keys($masterTables))) {
            $selectedTable = $_GET['table'];
            error_log('Selected table from GET: ' . $selectedTable);
            $sql = "SELECT * FROM `$selectedTable`";
            if ($selectedTable === 'genba_master') {
                $sql .= " ORDER BY genba_id DESC";
            } elseif ($selectedTable === 'target_name') {
                $sql .= " ORDER BY category, name ASC";
            }
            error_log('Executing GET SQL: ' . $sql);
            $result = $conn->query($sql);
            if ($result) {
                error_log('GET query successful, including table_partial.php');
                include 'table_partial.php';
                exit;
            } else {
                error_log('GET query failed: ' . $conn->error);
            }
        }
    }
}

// 通常のページ読み込み時のテーブルデータ取得
if ($selectedTable && in_array($selectedTable, array_keys($masterTables))) {
    error_log('Normal page load, fetching table data for: ' . $selectedTable);
    $sql = "SELECT * FROM `$selectedTable`";
    if ($selectedTable === 'genba_master') {
        $sql .= " ORDER BY genba_id DESC";
    } elseif ($selectedTable === 'target_name') {
                $sql .= " ORDER BY category, name ASC";
            }
    error_log('Executing normal page load SQL: ' . $sql);
    $result = $conn->query($sql);
    if (!$result) {
        $error_message = "データの取得に失敗しました: (" . $conn->errno . ") " . $conn->error;
        error_log('Normal page load query failed: ' . $error_message);
    } else {
        error_log('Normal page load query successful');
    }
}
ini_set('display_errors', 0); // エラーをブラウザに表示しない
ini_set('log_errors', 1);    // エラーをログに記録する
ini_set('error_log', '/path/to/error.log'); // エラーログの出力先を指定
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マスタデータ編集</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="./css/staffing.css" rel="stylesheet">
</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="container mt-3">
            <h2 class="mb-4">マスタデータ編集</h2>

            <!-- テーブル選択フォーム -->
            <form method="get" action="" class="mb-4 d-flex align-items-center">
                <div class="mb-3 col-md-3 me-auto">
                    <label for="tableSelect" class="form-label">テーブルを選択:</label>
                    <select id="tableSelect" name="table" class="form-select col-md-4" onchange="this.form.submit()">
                        <option value="" disabled selected>テーブルを選択</option>
                        <?php foreach ($masterTables as $table => $columns): ?>
                            <option value="<?= $table ?>" <?php if ($selectedTable === $table) echo 'selected'; ?>><?= $table ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedTable): ?>
                    <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#editModal" onclick="prepareNewEntryModal()">
                        新規登録
                    </button>
                <?php endif; ?>
            </form>

            <!-- メッセージ表示 -->
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- データテーブル -->
            <?php if ($selectedTable && in_array($selectedTable, array_keys($masterTables)) && $result): ?>
                <div class="table-responsive">
                    <?php include 'table_partial.php'; ?>
                </div>
            <?php endif; ?>

            <!-- 編集モーダル -->
            <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel">データ編集</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="editForm" method="post">
                            <div class="modal-body">
                                <input type="hidden" name="table" id="editTable">
                                <input type="hidden" name="id" id="editId" disabled>
                                <div id="editFields"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                                <button type="button" class="btn btn-primary" onclick="submitForm()">保存</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
        <?php include 'footer.php'; ?>
    </main>

    <script>
        // テーブル再取得用
        function refreshTable(table) {
            console.log('refreshTable called for table:', table);
            fetch(`${window.location.pathname}?table=${table}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('refreshTable response status:', response.status);
                    if (!response.ok) {
                        throw new Error('テーブルデータ取得に失敗');
                    }
                    return response.text();
                })
                .then(html => {
                    document.querySelector('.table-responsive').innerHTML = html;
                    attachEditButtonListeners();
                })
                .catch(err => {
                    alert('エラー: ' + err.message);
                    console.error(err);
                });
        }

        // finished カラムのトグル処理
        function toggleFinished(toggleSwitch) {
            // トグルスイッチのdata-record-id属性からレコードIDを取得
            const recordId = toggleSwitch.dataset.recordId;
            console.log('recordId:', recordId);
            // ドロップダウンリストからテーブル名を取得
            const table = document.getElementById('tableSelect').value;
            console.log('table:', table);
            // トグルスイッチの状態に応じて値を決定。チェックされていれば0（非表示）、そうでなければ1（表示）
            const value = toggleSwitch.checked ? 0 : 1;
            console.log('value:', value);

            let columnName = '';
            if (table === 'genba_master') {
                columnName = 'finished';
            } else if (table === 'checker_master') {
                columnName = 'hidden';
            } else if (table === 'target_name') {
                columnName = 'hidden';
            }
             else {
                console.error('不明なテーブル:', table);
                alert('不明なテーブルです。');
                return;
            }

            let bodyContent = `action=toggleChange&table=${table}`;
            if (table === 'genba_master') {
                bodyContent += `&genba_id=${recordId}&finished=${value}`;
            } else if (table === 'checker_master') {
                bodyContent += `&checker_id=${recordId}&hidden=${value}`;
            } else if (table === 'target_name') {
                bodyContent += `&id=${recordId}&hidden=${value}`;
            }
            console.log('body:', bodyContent);

            // fetch APIを使用してサーバーに非同期リクエストを送信
            fetch(window.location.pathname, { // 現在のURLをベースURLとして使用（URL重複を防ぐため）
                    method: 'POST', // POSTメソッドを使用
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded', // Content-TypeをURLエンコード形式に設定
                        'X-Requested-With': 'XMLHttpRequest' // XMLHttpRequestヘッダーを設定（AJAXリクエストであることをサーバーに伝える）
                    },
                    // リクエストボディに送信するデータ。action, table, id, column, value を指定
                    body: bodyContent
                })
                .then(response => {
                    // レスポンスがHTTPステータスコードOK（200番台）でない場合、エラーをthrow
                    console.log('toggleFinished response status:', response.status);
                    if (!response.ok) {
                        console.log('response.status:', response.status); // レスポンスステータスをログ出力
                        response.text().then(text => console.log('response.text:', text)); // レスポンス本文をログ出力
                        throw new Error('更新に失敗しました');
                    }
                    // レスポンスボディをJSON形式で解析
                    return response.json();
                })
                .then(result => {
                    // JSONレスポンスのsuccessプロパティがfalseの場合、エラーをthrow
                    if (!result.success) {
                        throw new Error(result.message || '更新に失敗しました');
                    }
                    // サーバーでの更新が成功した場合、テーブルをリフレッシュ
                    refreshTable(table);
                })
                .catch(error => {
                    // エラーが発生した場合の処理
                    console.error('エラー:', error); // コンソールにエラーログを出力
                    alert('エラーが発生しました: ' + error.message); // アラートメッセージを表示
                    toggleSwitch.checked = !toggleSwitch.checked; // エラー時はトグルスイッチの状態を元に戻す
                });
        }


        // モーダルに表示するデータ準備
        function attachEditButtonListeners() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', () => {
                    console.log('Edit button clicked');
                    // ボタンのdataset.recordからレコードデータを取得し、JSON文字列をJavaScriptオブジェクトに変換します。
                    const recordData = JSON.parse(button.dataset.record);
                    const table = button.dataset.table;
                    // 主キー名を取得。recordDataにprimary_keyプロパティがあればそれを使い、なければ'id'を使う
                    const primaryKey = recordData.primary_key || 'id';

                    document.getElementById('editTable').value = table;
                    document.getElementById('editFields').innerHTML = '';

                    const firstKey = Object.keys(recordData)[0];
                    Object.entries(recordData).forEach(([key, value]) => {
                        if ((table === 'genba_master' && key === 'finished') || (table === 'checker_master' && key === 'hidden') || (table === 'target_name' && key === 'hidden')) {
                            return; // genba_masterテーブルのfinishedカラム、checker_masterテーブルとtarget_nameテーブルのhiddenカラムの場合は処理をスキップ
                        }
                        const div = document.createElement('div');
                        div.className = 'mb-3';

                        let inputElement;
                        if (key === firstKey) { // 最初のフィールド（通常ID）
                            inputElement = `<input type="text" class="form-control" name="${key}" value="${value}" readonly style="background-color: #eee; pointer-events: none;">`;
                        }  else if ((table === 'checker_master' || table === 'target_name') && key === 'ob_type') {
                            // checker_masterテーブルまたはtarget_nameテーブルのob_typeの場合、ボタン選択式にする
                            if (table === 'target_name') {
                                // value: 5=自社, 12=リース
                                inputElement = `
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_5" value="5" ${(value == '5' || value == 5) ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_5">自社</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_12" value="12" ${(value == '12' || value == 12) ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_12">リース</label>
                                            </div>
                                        </div>
                                    </div>
                                `;

                            } else if (table === 'checker_master' && key === 'ob_type') {
                                // checker_masterテーブルのob_typeの場合、ボタン選択式にする
                                inputElement = `
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_1" value="1" ${value == '1' ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_1">男性</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_2" value="2" ${value == '2' ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_2">女性</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_4" value="4" ${value == '4' ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_4">技術屋</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_10" value="10" ${value == '10' ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_10">環境</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_11" value="11" ${value == '11' ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_11">社外</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_8" value="8" ${value == '8' ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_8">男性(新人)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="${key}" id="ob_type_9" value="9" ${value == '9' ? 'checked' : ''}>
                                                <label class="form-check-label" for="ob_type_9">女性(新人)</label>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }
                        } else if (table === 'target_name' && key === 'category') {
                            // target_nameテーブルのcategoryの場合、ドロップダウン選択式にする
                            inputElement = `
                                <select class="form-select" name="${key}">
                                    <option value="バックホウ" ${value == 'バックホウ' ? 'selected' : ''}>バックホウ</option>
                                    <option value="不整地運搬車" ${value == '不整地運搬車' ? 'selected' : ''}>不整地運搬車</option>
                                    <option value="ブル" ${value == 'ブル' ? 'selected' : ''}>ブル</option>
                                    <option value="クローラークレーン" ${value == 'クローラークレーン' ? 'selected' : ''}>クローラークレーン</option>
                                    <option value="コンバインドローラー" ${value == 'コンバインドローラー' ? 'selected' : ''}>コンバインドローラー</option>
                                    <option value="除雪車" ${value == '除雪車' ? 'selected' : ''}>除雪車</option>
                                    <option value="その他" ${value == 'その他' ? 'selected' : ''}>その他</option>
                                </select>
                            `;
                        } else {
                            // カラム名が display_name または short_name の場合はテキストエリアを使用
                            if (key === 'display_name' || key === 'short_name') {
                                inputElement = `<textarea class="form-control" name="${key}">${value}</textarea>`;
                            } else {
                                inputElement = `<input type="text" class="form-control" name="${key}" value="${value}">`;
                            }
                        }

                        // ラベル名の変更
                        let labelText = key;
                        switch (labelText) {
                            case 'finished':
                                labelText = 'finished';
                                break;
                            case 'hidden':
                                labelText = 'hidden';
                                break;
                            case 'checker_name':
                                labelText = '点検者名 (姓と名の間に半角スペースを入れてください)';
                                break;
                            case 'checker_phonetic':
                                labelText = 'ふりがな';
                                break;
                            case 'genba_name':
                                labelText = '現場名';
                                break;
                            case 'item_name':
                                labelText = '点検内容';
                                break;
                            case 'inspection_type_id':
                                labelText = '点検種別ID (inspection_type_idテーブルのtype_idに対応)';
                                break;
                            case 'sub':
                                labelText = 'サブ項目 (空欄可)';
                                break;
                            case 'sort':
                                labelText = '表示順';
                                break;
                            case 'category':
                                labelText = 'カテゴリ';
                                break;
                            case 'name':
                                labelText = '名称';
                                break;
                            case 'abbreviation':
                                labelText = '略称(空欄可)';
                                break;
                            case 'short_name':
                                labelText = ' 配置表に表示される文字列　(改行可)';
                                break;
                            case 'display_name':
                                labelText = ' 配置表に表示される文字列　(改行可)';
                                break;
                            case 'ob_type':
                                labelText = '表示タイプ'; // ob_type のラベル
                                break;
                        }

                        div.innerHTML = `
                      <label class="form-label">${labelText}:</label>
                      ${inputElement}
                    `;
                        document.getElementById('editFields').appendChild(div);
                    });
                });
            });
        }

        function prepareNewEntryModal() {
            console.log('prepareNewEntryModal called');
            const table = document.getElementById('tableSelect').value;
            document.getElementById('editTable').value = table;
            document.getElementById('editFields').innerHTML = '';

            // 既存のフィールドを取得して空のinputを作成
            const existingFields = document.querySelector('.table-responsive table thead tr');

            if (existingFields) {
                Array.from(existingFields.children).slice(1).forEach((th, index) => {
                    let fieldName = th.textContent;
                    const div = document.createElement('div');
                    div.className = 'mb-3';

                    let inputElement;
                    if (index === 0 && fieldName.includes('id')) { // 最初のカラムが 'id' を含む場合、変更不能表示にする
                        inputElement = `<input type="text" class="form-control" value="自動採番" disabled>`;
                    } else if (fieldName === 'finished' || fieldName === 'hidden') {
                        // finishedまたはhiddenの場合、変更不能inputを表示し、値は常に"0"を送信
                        inputElement = `
                            <input type="text" class="form-control" value="0" disabled>
                            <input type="hidden" name="${fieldName}" value="0">
                        `;
                    } else if ((table === 'genba_master' && fieldName === 'finished')) {
                        // genba_masterテーブルのfinishedの場合、トグルスイッチにする
                        inputElement = `
                            <div class="form-check form-switch d-flex align-items-center">
                                <input type="hidden" name="${fieldName}" id="hidden_${fieldName}" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="${fieldName}" name="${fieldName}" value="1" onchange="updateHiddenValue('${fieldName}')">
                                <label class="form-check-label ms-2" for="${fieldName}">表示</label>
                            </div>
                        `;
                    } else if ((table === 'checker_master' || table === 'target_name') && fieldName === 'ob_type') {
                        // checker_masterテーブルまたはtarget_nameテーブルのob_typeの場合、ボタン選択式にする
                        if (table === 'target_name') {
                            inputElement = `
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_5" value="5" checked>
                                            <label class="form-check-label" for="ob_type_5">自社</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_12" value="12">
                                            <label class="form-check-label" for="ob_type_12">リース</label>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else { // checker_master の場合 (現状維持)
                            inputElement = `
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_1" value="1" checked>
                                            <label class="form-check-label" for="ob_type_1">男性</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_2" value="2">
                                            <label class="form-check-label" for="ob_type_2">女性</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_4" value="4">
                                            <label class="form-check-label" for="ob_type_4">技術屋</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_10" value="10">
                                            <label class="form-check-label" for="ob_type_10">環境</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_11" value="11">
                                            <label class="form-check-label" for="ob_type_11">社外</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_8" value="8">
                                            <label class="form-check-label" for="ob_type_8">男性(新人)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${fieldName}" id="ob_type_9" value="9">
                                            <label class="form-check-label" for="ob_type_9">女性(新人)</label>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    } else if (table === 'target_name' && fieldName === 'category') {
                        // target_nameテーブルのcategoryの場合、ドロップダウン選択式にする
                        inputElement = `
                            <select class="form-select" name="${fieldName}">
                                <option value="バックホウ">バックホウ</option>
                                <option value="不整地運搬車">不整地運搬車</option>
                                <option value="ブル">ブル</option>
                                <option value="クローラークレーン">クローラークレーン</option>
                                <option value="コンバインドローラー">コンバインドローラー</option>
                                <option value="除雪車">除雪車</option>
                                <option value="除雪車">その他</option>
                            </select>
                        `;
                    } else {
                        // カラム名が display_name または short_name の場合はテキストエリアを使用
                        if (fieldName === 'display_name' || fieldName === 'short_name') {
                            inputElement = `<textarea class="form-control" name="${fieldName}"></textarea>`;
                        } else {
                            inputElement = `<input type="text" class="form-control" name="${fieldName}" value="">`;
                        }
                    }

                    // ラベル名の変更 (attachEditButtonListeners と同じロジックを使用)
                    let labelText = fieldName;
                    switch (labelText) {
                        case 'finished':
                            labelText = 'finished';
                            break;
                        case 'hidden':
                            labelText = 'hidden';
                            break;
                        case 'checker_name':
                            labelText = '点検者名 (姓と名の間に半角スペースを入れてください)';
                            break;
                        case 'checker_phonetic':
                            labelText = 'ふりがな';
                            break;
                        case 'genba_name':
                            labelText = '現場名';
                            break;
                        case 'item_name':
                            labelText = '点検内容';
                            break;
                        case 'inspection_type_id':
                            labelText = '点検種別ID (inspection_type_idテーブルのtype_idに対応)';
                            break;
                        case 'sub':
                            labelText = 'サブ項目 (空欄可)';
                            break;
                        case 'sort':
                            labelText = '表示順';
                            break;
                        case 'category':
                            labelText = 'カテゴリ';
                            break;
                        case 'name':
                            labelText = '名称';
                            break;
                        case 'abbreviation':
                            labelText = '略称(空欄可)';
                            break;
                        case 'short_name':
                            labelText = ' 配置表に表示される文字列　(改行可)';
                            break;
                        case 'display_name':
                            labelText = ' 配置表に表示される文字列　(改行可)';
                            break;
                        case 'ob_type':
                            labelText = '表示タイプ'; // ob_type のラベル
                            break;
                    }


                    div.innerHTML = `
                <label class="form-label">${labelText}:</label>
                ${inputElement}
            `;
                    document.getElementById('editFields').appendChild(div);

                    console.log(`Added field: ${fieldName}`); // デバッグログ
                });
            }
        }
        // フォーム送信(更新処理)
        function submitForm() {
            console.log('submitForm called');
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            const table = formData.get('table');
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });

            const errors = validateFormData(data, table);
            if (errors.length > 0) {
                alert(errors.join('\n'));
                return; // バリデーションエラーがある場合はここで処理を中断
            }


            console.log('Form Data:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }

            // URLから重複を防ぐ
            const baseUrl = window.location.href.split('&')[0];

            fetch(baseUrl, { // アクションパラメータを削除
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('submitForm response status:', response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`サーバーエラー: ${response.status}\n${text}`);
                        });
                    }
                    return response.json();
                })
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.message || '登録/更新に失敗しました');
                    }

                    // モーダルを閉じる処理を明示的に行う
                    const modalElement = document.getElementById('editModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    } else {
                        // モーダルインスタンスが見つからない場合
                        modalElement.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.remove();
                        }
                    }

                    // テーブルを更新
                    refreshTable(table);
                })
                .catch(error => {
                    console.error('エラーの詳細:', error);
                    alert('エラーが発生しました: ' + error.message);
                });
        }
        document.addEventListener('DOMContentLoaded', () => {
            attachEditButtonListeners();
        });
        // フォーム送信(更新処理)
        function validateFormData(data, table) {
            console.log('validateFormData called with data:', data, 'and table:', table);
            const errors = [];

            if (table === 'checker_master' || table === 'genba_master' || table === 'inspection_items' || table === 'inspection_master' || table === 'inspection_types' || table === 'target_name') {
                // 全テーブル共通: sub 以外のカラムは必須
                for (const key in data) {
                    if (key !== 'id' && key !== 'sub' && (!data[key] || data[key].trim() === '')) {
                        errors.push(`${key}は必須です。`);
                    }
                }
            }

            return errors;
        }
    </script>
</body>

</html>