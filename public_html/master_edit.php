<?php
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';
require_once 'config.php';
require_once 'functions.php';

$aollwed_users = ['田中利憲', '本山塁', '杉本義夫', '小島聡明', '小山哲郎', '藤村英明'];
$username = $_SESSION['display_name'] ?? '';

if (ob_get_level()) ob_end_flush();
$conn = connectDB();

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
        header('Content-Type: application/json');
        try {
            error_log('POST request received: ' . print_r($_POST, true));

            // POSTされたデータからテーブル名を取得
            $table = $_POST['table'];

            // 設定ファイルから主キー名を取得
            $primaryKey = $masterTables[$table]['primary_key'];

            // フォームから送信された主キーの値を取得
            $id = $_POST[$primaryKey] ?? null;

            if (!array_key_exists($table, $masterTables)) {
                throw new Exception('Invalid table name');
            }

            // データの準備（主キーと'table'を除外）
            $data = [];
            foreach ($_POST as $key => $value) {
                if ($key !== 'table' && $key !== $primaryKey) {
                    $data[$key] = $value;
                }
            }

            // IDがある場合はUPDATE、ない場合はINSERT
            if (!empty($id)) {
                // UPDATE処理
                error_log('Performing UPDATE');

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
                error_log('Params: ' . print_r($params, true));

                $stmt = $conn->prepare($updateSql);
                if (!$stmt) {
                    throw new Exception('Failed to prepare update statement: ' . $conn->error);
                }

                $stmt->bind_param($types, ...$params);

                if (!$stmt->execute()) {
                    throw new Exception('Failed to execute update statement: ' . $stmt->error);
                }

                $result = [
                    'success' => true,
                    'message' => '更新が完了しました。',
                    'affected_rows' => $stmt->affected_rows
                ];
            } else {
                // INSERT処理
                error_log('Performing INSERT');

                // データが空の場合はエラー
                if (empty($data)) {
                    throw new Exception('No data to insert');
                }

                $insertSql = "INSERT INTO `$table` (" . implode(', ', array_keys($data)) . ") VALUES (" .
                    implode(', ', array_fill(0, count($data), '?')) . ")";

                error_log('Insert SQL: ' . $insertSql);
                error_log('Insert Data: ' . print_r($data, true));

                $stmt = $conn->prepare($insertSql);
                if (!$stmt) {
                    throw new Exception('Failed to prepare insert statement: ' . $conn->error);
                }

                $types = str_repeat('s', count($data));
                $stmt->bind_param($types, ...array_values($data));

                if (!$stmt->execute()) {
                    throw new Exception('Failed to execute insert statement: ' . $stmt->error);
                }

                $result = [
                    'success' => true,
                    'message' => '新規登録が完了しました。',
                    'affected_rows' => $stmt->affected_rows,
                    'last_insert_id' => $conn->insert_id
                ];
            }

            // 結果を返す
            echo json_encode($result);
            $stmt->close();
        } catch (Exception $e) {
            error_log('Exception: ' . $e->getMessage());
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
        if (in_array($_GET['table'], array_keys($masterTables))) {
            $selectedTable = $_GET['table'];
            $sql = "SELECT * FROM `$selectedTable`";
            $result = $conn->query($sql);
            if ($result) {
                include 'table_partial.php';
                exit;
            }
        }
    }
}

// 通常のページ読み込み時のテーブルデータ取得
if ($selectedTable && in_array($selectedTable, array_keys($masterTables))) {
    $sql = "SELECT * FROM `$selectedTable`";
    $result = $conn->query($sql);
    if (!$result) {
        $error_message = "データの取得に失敗しました: (" . $conn->errno . ") " . $conn->error;
    }
}
if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    throw new Exception('Database connection failed');
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
    </main>
    <?php include 'footer.php'; ?>
    <script>
        // テーブル再取得用
        function refreshTable(table) {
            fetch(`${window.location.pathname}?table=${table}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
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

        // モーダルに表示するデータ準備
        function attachEditButtonListeners() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', () => {
                    // ボタンのdataset.recordからレコードデータを取得し、JSON文字列をJavaScriptオブジェクトに変換します。
                    const recordData = JSON.parse(button.dataset.record);
                    const table = button.dataset.table;
                    // 主キー名を取得。recordDataにprimary_keyプロパティがあればそれを使い、なければ'id'を使う
                    const primaryKey = recordData.primary_key || 'id';

                    document.getElementById('editTable').value = table;
                    document.getElementById('editFields').innerHTML = '';

                    const firstKey = Object.keys(recordData)[0];
                    Object.entries(recordData).forEach(([key, value]) => {
                        const div = document.createElement('div');
                        div.className = 'mb-3';

                        let inputElement;
                        if (key === firstKey) { // 最初のフィールド（通常ID）
                            inputElement = `<input type="text" class="form-control" name="${key}" value="${value}" readonly style="background-color: #eee; pointer-events: none;">`;
                        } else {
                            inputElement = `<input type="text" class="form-control" name="${key}" value="${value}">`;
                        }

                        // ラベル名の変更
                        let labelText = key;
                        switch (labelText) {
                            case 'finished':
                            case 'hidden':
                                labelText = '表示設定 (0が表示、1が非表示)';
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
                                fieldName = '略称(空欄可)';
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
                    if (index === 0) { // 最初のフィールド（通常ID）
                        inputElement = `<input type="text" class="form-control" name="${fieldName}" readonly style="background-color: #eee; pointer-events: none;">`;
                    } else {
                        inputElement = `<input type="text" class="form-control" name="${fieldName}" value="">`;
                    }

                    // ラベル名の変更
                    switch (fieldName) {
                        case 'finished':
                        case 'hidden':
                            fieldName = '表示設定 (0が表示、1が非表示)';
                            break;
                        case 'checker_name':
                            fieldName = '点検者名 (姓と名の間に半角スペースを入れてください)';
                            break;
                        case 'checker_phonetic':
                            fieldName = 'ふりがな';
                            break;
                        case 'genba_name':
                            fieldName = '現場名';
                            break;
                        case 'item_name':
                            fieldName = '点検内容';
                            break;
                        case 'inspection_type_id':
                            fieldName = '点検種別ID (inspection_type_idテーブルのtype_idに対応)';
                            break;
                        case 'sub':
                            fieldName = 'サブ項目 (空欄可)';
                            break;
                        case 'sort':
                            fieldName = '表示順';
                            break;
                        case 'category':
                            fieldName = 'カテゴリ';
                            break;
                        case 'name':
                            fieldName = '名称';
                            break;
                        case 'abbreviation':
                            fieldName = '略称(空欄可)';
                            break;
                    }

                    div.innerHTML = `
                <label class="form-label">${fieldName}:</label>
                ${inputElement}
            `;
                    document.getElementById('editFields').appendChild(div);

                    console.log(`Added field: ${fieldName}`); // デバッグログ
                });
            }
        }
        // フォーム送信(更新処理)
        function submitForm() {
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            const table = formData.get('table');

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
            const errors = [];

            if (table === 'checker_master' || table === 'genba_master') {
                // checker_master、genba_masterのバリデーション
                for (const key in data) {
                    if (key !== 'id' && (!data[key] || data[key].trim() === '')) {
                        errors.push(`${key}は必須です。`);
                    }
                }
            } else if (table === 'target_name') {
                // target_nameのバリデーション
                for (const key in data) {
                    if (key !== 'id' && key !== 'abbreviation' && (!data[key] || data[key].trim() === '')) {
                        errors.push(`${key}は必須です。`);
                    }
                }
            }

            return errors;
        }
    </script>
</body>

</html>