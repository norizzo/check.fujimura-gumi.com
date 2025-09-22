<?php
// view_records.php
if (ob_get_level()) ob_end_flush();
// 必要なファイルを読み込む
require_once 'config.php'; // DB接続ファイル
require_once 'functions.php'; // sanitizeInput 関数など

// データベースに接続
$conn = connectDB();

// フィルターフォームのデータ取得
$genba_id = isset($_GET['genba_id']) ? intval($_GET['genba_id']) : '';
$inspection_type_id = isset($_GET['inspection_type_id']) ? intval($_GET['inspection_type_id']) : '';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// フィルタを適用するかどうか
$filter_applied = !empty($genba_id) && !empty($inspection_type_id) && !empty($month);
//var_dump($filter_applied);


// 現場名のドロップダウンを取得
$genba_sql = "SELECT genba_id, genba_name FROM genba_master WHERE finished != 1 ORDER BY genba_name ASC";
$genba_result = $conn->query($genba_sql);
if (!$genba_result) {
    die("現場名の取得に失敗しました: (" . $conn->errno . ") " . $conn->error);
}

// 点検種類を取得する関数
function getInspectionTypes($conn, $genba_id, $month)
{
    // 月の最終日を取得
    $year = date('Y', strtotime($month));
    $month_num = date('m', strtotime($month));

    // 現場と月に基づいて点検データを取得
    $sql = "
        SELECT DISTINCT it.type_id, it.name, it.category
        FROM inspections i
        JOIN inspection_result ir ON i.id = ir.inspection_id
        JOIN inspection_types it ON i.inspection_type_id = it.type_id
        WHERE i.genba_id = ?
            AND DATE_FORMAT(i.date, '%Y-%m') = ?
            ORDER BY it.category, it.name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $genba_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();

    $inspection_types = [];
    while ($row = $result->fetch_assoc()) {
        $inspection_types[$row['category']][] = [
            'id' => $row['type_id'],
            'name' => sanitizeInput($row['name'])
        ];
    }
    $stmt->close();
    return $inspection_types;
}

// 点検種類を取得
$inspection_types = getInspectionTypes($conn, $genba_id, $month);

// 点検項目を取得 (inspection_items) based on selected inspection_type_id
$inspection_items = [];
if ($filter_applied) {
    $items_sql = "SELECT item_id, item_name FROM inspection_items WHERE inspection_type_id = ? ORDER BY item_id ASC";
    $stmt = $conn->prepare($items_sql);
    $stmt->bind_param("i", $inspection_type_id);
    $stmt->execute();
    $result_items = $stmt->get_result();
    while ($item = $result_items->fetch_assoc()) {
        $inspection_items[$item['item_id']] = $item['item_name'];
    }
    $stmt->close();
}

// 点検データを取得
$inspection_data = [];

if ($filter_applied) {
    $sql = "
        SELECT 
            i.id AS inspection_id,
            i.date,
            ir.item_id,
            ir.result_value
        FROM inspections i
        JOIN inspection_result ir ON i.id = ir.inspection_id
        WHERE i.genba_id = ?
          AND i.inspection_type_id = ?
          AND DATE_FORMAT(i.date, '%Y-%m') = ?
        ORDER BY i.date ASC, ir.item_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $genba_id, $inspection_type_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $inspection_id = $row['inspection_id'];
        $item_id = $row['item_id'];
        $result_value = $row['result_value'];

        if (!isset($inspection_data[$date])) {
            $inspection_data[$date] = ['inspection_id' => $inspection_id, 'items' => []];
        }
        $inspection_data[$date]['items'][$item_id] = $result_value;
    }
    // var_dump($inspection_data);
    $stmt->close();
    ksort($inspection_items);
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Language" content="ja"> <!-- 日本語を指定 -->
    <title>現場ビュー</title>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive {
            overflow-x: auto;
        }

        th,
        td {
            white-space: nowrap;
            font-size: x-small;
            padding-left: 0.rem;
            padding-right: 0.rem;
            text-align: center;
        }



        /* 先頭カラムを固定する */
        .table-responsive thead th:first-child,
        .table-responsive tbody td:first-child {
            position: sticky;
            left: 0;
            background-color: #f8f9fa;
            /* テーブル背景色を設定 */
            z-index: 2;
            /* 他のカラムの上に表示 */
        }

        footer {
            position: relative;
            bottom: 0;
            width: 100%;
            padding: 10px;
            background-color: #f8f9fa;
            text-align: center;
        }

        #printTitle {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 0px;
            display: flex;
            justify-content: center;
            /* 中央寄せ */
            align-items: center;
            /* 垂直方向に中央寄せ */
        }

        #monthTitle,
        #projectTitle,
        #inspectionTitle {
            margin-right: 40px;
            /* 各要素の間にスペースを追加 */
        }

        @media print {
            body * {
                visibility: hidden;
                /* 印刷エリア以外を非表示に */
            }

            #printArea,
            #printArea * {
                visibility: visible;
                /* 印刷エリア内の要素を表示 */
            }

            #printArea {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                /* 印刷エリアの位置を調整して最上部に配置 */
            }

            /* テーブルをページの上部に配置 */
            #printTable {
                width: 100%;
                margin-top: 0;
                padding-top: 0;
            }

            /* フォームの空白を削除 */
            #filter-form {
                display: none;
            }

            /* .table_title内の「：」とその後の内容を分けて折り返しやすくする */
            #table_title {
                display: flex;
                flex-wrap: wrap;
                gap: 3px;
                /* 「月：」とその後の間隔 */
            }

            #table_title span {
                white-space: normal;
                /* 折り返しを許可 */
            }

            table td,
            table th {
                font-size: 6pt;
                /* テーブル内のセルのフォントサイズを小さく */
                padding: 0px;
                /* セルの余白を調整 */
            }
        }
    </style>

</head>

<body>

    <div class="container mt-5">
        <h2>点検状況</h2>

        <!-- フィルターフォーム -->
        <form method="GET" action="view_records.php" class="mb-4" id="filter-form">
            <div class="form-row">
                <!-- 現場名ドロップダウン -->
                <div class="form-group col-md-4 mb-2">
                    <label for="genba_id">現場名</label>
                    <select id="genba_id" name="genba_id" class="form-control" required>
                        <option value="" disabled <?php echo empty($genba_id) ? 'selected' : ''; ?>>選択してください</option>
                        <?php while ($row = $genba_result->fetch_assoc()) { ?>
                            <option value="<?php echo intval($row['genba_id']); ?>" <?php echo ($row['genba_id'] == $genba_id) ? 'selected' : ''; ?>>
                                <?php echo sanitizeInput($row['genba_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <!-- 対象月カレンダー -->
                <div class="form-group col-md-4 mb-2">
                    <label for="month">対象月</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?php echo sanitizeInput($month); ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">表示</button>
        </form>

        <!-- 点検結果テーブル -->
        <div class="table-responsive">
            <div id="printArea">
                <!-- 印刷時に表示する表題 -->
                <div id="printTitle">
                    <p id="table_title"></p>
                </div>
                <table id="printTable" class="table table-bordered">
                    <thead>
                        <tr>

                            <?php
                            if ($filter_applied) {
                                $selected_month = $_GET['month'] ?? date('Y-m');

                                // 月の最終日を取得
                                $year = date('Y', strtotime($selected_month));
                                $month_num = date('m', strtotime($selected_month));
                                $last_day = date('t', strtotime($selected_month));
                                echo "<th class = 'text-center'>点検項目</th>";
                                // 1日から最終日までの日付を表示
                                for ($day = 1; $day <= $last_day; $day++) {
                                    echo "<th class='text-center table-light'>" . $day . "</th>";
                                }
                            }
                            ?>

                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($filter_applied && !empty($inspection_items)) {
                            // 点検者名を取得するSQLクエリ
                            $inspector_sql = "
                            SELECT c.checker_name AS inspector_name
                            FROM inspections i
                            JOIN checker_master c ON i.checker_id = c.checker_id
                            WHERE i.inspection_type_id = ?
                            AND i.genba_id = ?
                            AND i.date = ?";


                            // 最初の行に「点検者」を表示
                            echo "<tr>";
                            echo "<td class='text-start'>点検者</td>";

                            // 点検者名を表示するための変数を用意
                            $inspector_name = '';

                            // 1日から最終日までの日付をループ
                            for ($day = 1; $day <= $last_day; $day++) {
                                $current_date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                $stmt_inspector = $conn->prepare($inspector_sql);
                                $stmt_inspector->bind_param("iis", $inspection_type_id, $genba_id, $current_date);
                                $stmt_inspector->execute();
                                $result_inspector = $stmt_inspector->get_result();

                                if ($result_inspector->num_rows > 0) {
                                    $inspector_row = $result_inspector->fetch_assoc();
                                    $inspector_name = $inspector_row['inspector_name'] ?? '';
                                    $inspector_name_h = explode(' ', $inspector_name)[0];
                                } else {
                                    $inspector_name_h = ''; // データがない場合は「－」を表示
                                }
                                // 点検者名を表示
                                echo "<td data-bs-toggle='tooltip' title='" . sanitizeInput($inspector_name) . "' data-placement='bottom' >" . sanitizeInput($inspector_name_h) . "</td>";
                                $stmt_inspector->close();
                            }
                            echo "</tr>";

                            // 各inspection_itemを行として表示
                            foreach ($inspection_items as $item_id => $item_name) {
                                echo "<tr>";
                                echo "<td class='text-start text-wrap' style='width: 10rem'> " . sanitizeInput($item_name) . "</td>";

                                // 1日から最終日までの日付をループして表示
                                for ($day = 1; $day <= $last_day; $day++) {
                                    $current_date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);

                                    // 該当するデータがあれば表示、なければ「-」
                                    if (isset($inspection_data[$current_date])) {
                                        $data = $inspection_data[$current_date];
                                        $result_value = isset($data['items'][$item_id]) ? sanitizeInput($data['items'][$item_id]) : '-';
                                        $inspection_id = $data['inspection_id'];

                                        if ($inspection_id !== null) {
                                            echo "<td class='text-center  px-1 align-middle'><data-toggle='modal' data-target='#editModal' 
                                            data-inspection_id='" . $inspection_id . "' 
                                            data-item_id='" . $item_id . "' 
                                            data-result_value='" . $result_value . "' 
                                            data-date='" . sanitizeInput($current_date) . "'>" . $result_value . "</td>";
                                        } else {
                                            echo "<td class='align-middle'></td>";
                                        }
                                    } else {
                                        echo "<td class='align-middle'></td>";
                                    }
                                }
                                echo "</tr>";
                            }

                            // 備考を表示する行
                            echo "<tr>";
                            echo "<td class='text-start'>備考</td>";

                            for ($day = 1; $day <= $last_day; $day++) {
                                $current_date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);

                                // コメントを取得
                                $comments_sql = "
                                    SELECT comments
                                    FROM inspections
                                    WHERE inspection_type_id = ? 
                                    AND genba_id = ? 
                                    AND date = ?";
                                $stmt_comments = $conn->prepare($comments_sql);
                                $stmt_comments->bind_param("iis", $inspection_type_id, $genba_id, $current_date);
                                $stmt_comments->execute();
                                $result_comments = $stmt_comments->get_result();
                                $comment_row = $result_comments->fetch_assoc();
                                $comment_value = $comment_row['comments'] ?? null;

                                // コメントの表示
                                if (!empty($comment_value)) {
                                    echo "<td class= 'px-1 text-center align-middle'><a href='#' onclick='alert(\"" . addslashes(sanitizeInput($comment_value)) . "\"); return false;'>あり</a></td>";
                                } else {
                                    echo "<td class= 'px-1 text-center align-middle'></td>";  // コメントがない場合
                                }
                                $stmt_comments->close();
                            }
                            echo "</tr>";
                        } else {
                            echo "<tr><td colspan='100%'>対象を選択して表示ボタンを押すと表示されます</td></tr>";
                        }

                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <button class="btn btn-secondary" onclick="printTable()">テーブルを印刷</button>
    </div>

    <div id="footer" style="display: none;">
        <?php include 'footer.php'; ?>
    </div>
    <script>
        $(document).ready(function() {
            // 現場名または対象月が変更されたときに点検種類を再読み込み
            $('#genba_id, #month').change(function() {
                var genba_id = $('#genba_id').val();
                var month = $('#month').val();

                // AJAXリクエストを送信して点検種類を取得
                $.ajax({
                    url: 'get_inspection_types.php',
                    method: 'GET',
                    data: {
                        genba_id: genba_id,
                        month: month
                    },
                    success: function(response) {
                        // $('#inspection_type_id').html(response); // 点検種類ドロップダウンを更新
                    }
                });
            });
        });
        /// ページ全体がロードされたらフッターを表示
        window.addEventListener("load", function() {
            document.getElementById("footer").style.display = "block";
        });
        
        function printTable() {
            // ドロップダウンから選択された値を取得
            var month = document.getElementById("month").value;
            var genbaSelect = document.getElementById("genba_id");
            var projectName = genbaSelect.options[genbaSelect.selectedIndex].text;
            var inspectionSelect = document.getElementById("inspection_type_id");
            var inspectionTypeName = inspectionSelect.options[inspectionSelect.selectedIndex].text;
            // 表題部分に選択された値をセット
            document.getElementById("table_title").innerHTML =
                month + "月：<span>" + projectName + "【" + inspectionTypeName + "】</span>";
                window.print(); // ページ印刷ダイアログを開く
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>