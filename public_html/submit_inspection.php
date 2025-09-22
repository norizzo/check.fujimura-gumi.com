<?php
//var_dump($_POST); //デバッグ用var_dump削除
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// 必要なファイルの読み込み
require_once 'config.php';
require_once 'functions.php';

// データベース接続
$conn = connectDB();

// POSTデータの取得
$date = $_POST['date'] ?? null;
$genba_id = $_POST['genba_id'] ?? null;
$checker_id = $_POST['checker_id'] ?? null;
$inspection_type_id = $_POST['inspection_type_id'] ?? null;
$inspection_item_name = $_POST['inspection_item_name'] ?? null;
$requestSource = $_POST['source'] ?? null;
$comments = $_POST['comments'] ?? '';
$inspection_item_name = htmlspecialchars($inspection_item_name, ENT_QUOTES, 'UTF-8');
$inspection_item_name = str_replace(["\r", "\n"], '', $inspection_item_name); // 改行を削除


// 必須項目の確認
if (empty($date) || empty($genba_id) || empty($checker_id) || empty($inspection_type_id)) {
    die("すべての必須項目を入力してください。");
}

// inspection_type_idと日付で既存データの確認
function checkDuplicateInspection($conn, $inspection_type_id, $date, $genba_id, $inspection_item_name)
{
    $sql = "SELECT * FROM inspections WHERE inspection_type_id = ? AND date = ? AND genba_id = ?";
    $params = [$inspection_type_id, $date, $genba_id];

    if (($inspection_type_id == 18 || $inspection_type_id == 30) && !empty($inspection_item_name)) {
        $sql .= " AND inspection_item_name = ?";
        $params[] = $inspection_item_name;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// 現場名または検査タイプ名の取得
function fetchSingleColumn($conn, $table, $column, $where, $value)
{
    $sql = "SELECT $column FROM $table WHERE $where = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()[$column] ?? "不明";
}

// アラート表示とリダイレクト処理
function showAlertAndRedirect($message, $genba_id, $inspection_type_id)
{
    $redirectUrl = getRedirectUrl($inspection_type_id);
    $queryParams = getQueryParams($genba_id, $inspection_type_id);

    // アラートを表示して、元のページにリダイレクト
    echo "<script>
        alert('{$message}');
        window.location.href = '{$redirectUrl}?{$queryParams}';
    </script>";
    exit();
}

// リダイレクトURLを取得
function getRedirectUrl($inspection_type_id)
{
    return ($inspection_type_id == 18) ? "get_staffing.php" : "inspection_form.php";
}

// クエリパラメータを取得
function getQueryParams($genba_id, $inspection_type_id)
{
    return http_build_query([
        'error' => 'duplicate',
        'genba_id' => $genba_id,
        'inspection_type_id' => $inspection_type_id
    ]);
}

// 既存データチェック
$result = checkDuplicateInspection($conn, $inspection_type_id, $date, $genba_id, $inspection_item_name);
if ($result->num_rows > 0) {
    $genba_name = fetchSingleColumn($conn, "genba_master", "genba_name", "genba_id", $genba_id);
    $inspection_type_name = fetchSingleColumn($conn, "inspection_types", "name", "type_id", $inspection_type_id);

    $alert_message = ($inspection_type_id == 18 || $inspection_type_id == 30)
        ? "既に登録済みです：現場名「{$genba_name}」、検査項目「{$inspection_item_name}」"
        : "既に登録済みです：現場名「{$genba_name}」、検査タイプ「{$inspection_type_name}」";

    $redirectUrl = $_SERVER['HTTP_REFERER']; // 元のURLを取得
    if(empty($redirectUrl)){
        $redirectUrl = "inspection_form.php"; // 元のURLが取得できない場合はデフォルトのURLを設定
    }
    $queryParams = getQueryParams($genba_id, $inspection_type_id);

    // アラートを表示して、元のページにリダイレクト
    echo "<script>
        alert('{$alert_message}');
        window.location.href = '{$redirectUrl}?{$queryParams}';
    </script>";
    exit();
}

// トランザクション開始
$conn->begin_transaction();
try {
    // inspectionsテーブルへの挿入
    $time = date('H:i:s');
    $insertInspectionSql = "INSERT INTO inspections (date, genba_id, time, checker_id, inspection_type_id, comments, inspection_item_name) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertInspectionSql);
    $stmt->bind_param("sisisss", $date, $genba_id, $time, $checker_id, $inspection_type_id, $comments, $inspection_item_name);
    $stmt->execute();
    $inspection_id = $stmt->insert_id;
    $stmt->close();

    // 点検結果の挿入
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'item_') === 0) {
            $item_id = intval(str_replace('item_', '', $key));
            $checkItemSql = "SELECT item_id FROM inspection_items WHERE item_id = ?";
            $checkStmt = $conn->prepare($checkItemSql);
            $checkStmt->bind_param("i", $item_id);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows == 0) {
                throw new Exception("無効な item_id: " . $item_id);
            }
            $checkStmt->close();

            $insertResultSql = "INSERT INTO inspection_result (inspection_id, item_id, result_value) VALUES (?, ?, ?)";
            $resultStmt = $conn->prepare($insertResultSql);
            $resultStmt->bind_param("iis", $inspection_id, $item_id, $value);
            $resultStmt->execute();
            $resultStmt->close();
        }
    }

    // ファイルアップロード処理
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file_name = uniqid('inspection_', true) . '.' . pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $uploadFile = $uploadDir . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
            $updateFileSql = "UPDATE inspections SET file_name = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateFileSql);
            $updateStmt->bind_param("si", $file_name, $inspection_id);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            throw new Exception("ファイルのアップロードに失敗しました。");
        }
    }

    // コミット
    $conn->commit();


    // 成功時のリダイレクト アラート表示を維持しつつ、一瞬表示される文字列を削除
    $redirectUrl = ($requestSource === 'get_staffing') ? 'get_staffing.php' : (($requestSource === 'generator') ? 'generator.php' : 'inspection_top.php');
    $alert_message = '点検データが正常に保存されました。';

    if ($requestSource === 'get_staffing' || $requestSource === 'generator') {
        echo "<script>
            alert('{$alert_message}');
            window.location.href = '{$redirectUrl}?genba_id={$genba_id}&inspection_type_id={$inspection_type_id}';
        </script>";
    } else {
        echo "<script>
            alert('{$alert_message}');
            // 親ウィンドウのモーダルを閉じてリロード
            window.parent.postMessage({ closeModal: true }, '*'); 
        </script>";
    }
    exit();
} catch (Exception $e) {
    error_log("エラー内容: " . $e->getMessage());
    $conn->rollback();
    die("エラーが発生しました: " . $e->getMessage());
} finally {
    closeDB($conn);
}
