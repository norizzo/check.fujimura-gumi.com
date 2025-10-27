<?php
// view_records.php
if (ob_get_level()) ob_end_flush();
// 必要なファイルを読み込む
require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) .  '/private/functions.php';

// auth.phpやauth_check.phpを共通で読み込む
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth_check.php';

// データベースに接続
$conn = connectDB();

// フィルターフォームのデータ取得
$genba_id = isset($_GET['genba_id']) ? intval($_GET['genba_id']) : '';
$inspection_type_id = isset($_GET['inspection_type_id']) ? intval($_GET['inspection_type_id']) : '';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// フィルタを適用するかどうか
$filter_applied = !empty($genba_id) && !empty($inspection_type_id) && !empty($month); // 現場ID、点検種類ID、対象月が全て入力されている場合にtrueとなります。
//var_dump($filter_applied);


// 現場名のドロップダウンを取得
$genba_sql = "SELECT genba_id, genba_name FROM genba_master ORDER BY genba_id ASC";
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
// inspection_item_nameを取得 (inspection_items) based on selected inspection_type_id
// $inspection_item_names = [];
// if ($filter_applied) {
//     $items_sql = "SELECT inspection_item_name,date FROM inspections WHERE inspection_type_id = ?";
//     $stmt = $conn->prepare($items_sql);
//     $stmt->bind_param("i", $inspection_type_id);
//     $stmt->execute();
//     $result_items = $stmt->get_result();
//     while ($item = $result_items->fetch_assoc()) {
//         $inspection_item_names[$item['date']] = $item['inspection_item_name'];
//     }
//     $stmt->close();
//     var_dump($inspection_item_names); // 配列の中身をコンソール表示
// }

// 点検データを取得
$inspection_data = [];
$inspection_data_id = [];
// $filter_applied が true になる条件: $genba_id, $inspection_type_id, $month の全てが空でない場合。これは、ユーザーが現場ID、点検種類ID、月を指定してフィルターを適用した場合に該当します。
if ($filter_applied) {
    $sql = "
        SELECT 
            i.id AS inspection_id,
            i.date,
            i.inspection_item_name,
            ir.item_id,
            ir.result_value
        FROM inspections i
        JOIN inspection_result ir ON i.id = ir.inspection_id
        WHERE i.genba_id = ?
          AND i.inspection_type_id = ?
          AND DATE_FORMAT(i.date, '%Y-%m') = ?
        ORDER BY i.date ASC, ir.item_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $genba_id, $inspection_type_id, $month,);
    $stmt->execute();
    $result = $stmt->get_result();

    // 各行のデータを取り出し、$inspection_data配列に格納する処理
    while ($row = $result->fetch_assoc()) {
        // 日付を取得
        $date = $row['date'];
        // 点検IDを取得
        $inspection_id = $row['inspection_id'];
        // 点検項目IDを取得
        $item_id = $row['item_id'];
        //item_name
        $item_name = $row['inspection_item_name'];

        // 点検結果を取得
        $result_value = $row['result_value'];


        // 日付が$inspection_data配列に存在しない場合、新しい配列を作成する
        if (!isset($inspection_data[$date])) {
            // 日付をキーとして、点検IDと空のitems配列を持つ連想配列を作成する
            $inspection_data[$date] = ['inspection_id' => $inspection_id, 'items' => []];
        }
        // items配列に、点検項目IDをキーとして点検結果を格納する
        $inspection_data[$date]['items'][$item_id] = $result_value;

        // 各行のデータを取り出し、$inspection_data_id配列に格納する処理
        $inspection_id = $row['inspection_id'];
        if (!isset($inspection_data_id[$inspection_id])) {
            $inspection_data_id[$inspection_id] = [
                'date' => $row['date'],
                'item_name' => $row['inspection_item_name'],
                'items' => []
            ];
        }
        $inspection_data_id[$inspection_id]['items'][] = [
            'item' => $row['result_value']
        ];
    
    
    }

    // デバッグ出力はコメントアウトを外して確認します。
    if (!empty($inspection_data_id)) {
        echo "<pre>";
        var_dump($inspection_data_id);
        echo "</pre>";
    }
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspection Records</title>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <scrip src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
        </script>
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

            .bg-lightblue {
                background-color: #d9f2ff !important;
                /* 薄い青 */
            }

            .bg-lightred {
                background-color: #ffe6e6 !important;
                /* 薄い赤 */
            }

            .saturday-column {
                background-color: #d9f2ff !important;
                /* 薄い青 */
            }

            .sunday-column {
                background-color: #ffe6e6 !important;
                /* 薄い赤 */
            }
        </style>

</head>

<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="container mt-5">
            <h2>点検結果</h2>

            <!-- フィルターフォーム -->
            <form method="GET" action="view_records.php" class="mb-4" id="filter-form">
                <div class="form-row">
                    <!-- 対象月カレンダー -->
                    <div class="form-group col-md-4 mb-2">
                        <label for="month">対象月</label>
                        <input type="month" id="month" name="month" class="form-control" value="<?php echo sanitizeInput($month); ?>" required>
                    </div>
                    <!-- 現場名ドロップダウン -->
                    <div class="form-group col-md-4 mb-2">
                        <label for="genba_id">現場名</label>
                        <select id="genba_id" name="genba_id" class="form-select" required>
                            <option value="" disabled <?php echo empty($genba_id) ? 'selected' : ''; ?>>選択してください</option>
                            <?php while ($row = $genba_result->fetch_assoc()) { ?>
                                <option value="<?php echo intval($row['genba_id']); ?>" <?php echo ($row['genba_id'] == $genba_id) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeInput($row['genba_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <!-- 点検種類ドロップダウン -->
                    <div class="form-group col-md-4 mb-2">
                        <label for="inspection_type_id">点検種類</label>
                        <select id="inspection_type_id" name="inspection_type_id" class="form-select" required data-bs-toggle='tooltip' title='点検実績が無い項目は表示されません' data-placement='right'>
                            <option value="" disabled <?php echo empty($inspection_type_id) ? 'selected' : ''; ?>>選択してください</option>
                            <?php if ($filter_applied): ?>
                                <?php // フィルターが適用されている場合、点検種類のオプションを生成 
                                ?>
                                <?php foreach ($inspection_types as $category => $types): ?>
                                    <?php // 各カテゴリごとにoptgroupを作成 
                                    ?>
                                    <optgroup label="<?php echo sanitizeInput($category); ?>">
                                        <?php foreach ($types as $type): ?>
                                            <?php // 各点検種類ごとにoptionを作成 
                                            ?>
                                            <option value="<?php echo intval($type['id']); ?>" <?php echo ($type['id'] == $inspection_type_id) ? 'selected' : ''; ?>>
                                                <?php echo sanitizeInput($type['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </select>
                    </div>
                </div>
                <!-- <button type="submit" class="btn btn-primary">表示</button> -->
            </form>

            <!-- 点検結果テーブル -->
            <div class="table-responsive">
                <div id="printArea">
                    <!-- 印刷時に表示する表題 -->
                    <div id="printTitle">
                        <p id="table_title"></p>
                    </div>
                    <table id="printTable" class="table table-bordered">
                        <?php

                        /**
                         * 指定された日付、現場ID、点検種類IDに対応する検査官の名前を取得します。
                         *
                         * @param object $conn データベース接続オブジェクト
                         * @param int $inspection_type_id 点検種類ID
                         * @param int $genba_id 現場ID
                         * @param string $date 日付 (YYYY-MM-DD形式)
                         * @return string 検査官の名前。該当データがない場合は空文字列を返します。
                         */
                        function getInspectorName($conn, $inspection_type_id, $genba_id, $date)
                        {
                            $inspector_sql = "
                            SELECT c.checker_name AS inspector_name
                            FROM inspections i
                            JOIN checker_master c ON i.checker_id = c.checker_id
                            WHERE i.inspection_type_id = ?
                            AND i.genba_id = ?
                            AND i.date = ?";

                            $stmt = $conn->prepare($inspector_sql);
                            $stmt->bind_param("iis", $inspection_type_id, $genba_id, $date);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $inspector_name = '';

                            if ($result->num_rows > 0) {
                                $inspector_row = $result->fetch_assoc();
                                $inspector_name = $inspector_row['inspector_name'] ?? '';
                            }

                            $stmt->close();
                            return $inspector_name;
                        }

                        /**
                         * 指定された日付、現場ID、点検種類IDに対応するコメントを取得します。
                         *
                         * @param object $conn データベース接続オブジェクト
                         * @param int $inspection_type_id 点検種類ID
                         * @param int $genba_id 現場ID
                         * @param string $date 日付 (YYYY-MM-DD形式)
                         * @return string|null コメント。該当データがない場合はnullを返します。
                         */
                        function getComment($conn, $inspection_type_id, $genba_id, $date)
                        {
                            $comments_sql = "
                            SELECT comments
                            FROM inspections
                            WHERE inspection_type_id = ? 
                            AND genba_id = ? 
                            AND date = ?";

                            $stmt = $conn->prepare($comments_sql);
                            $stmt->bind_param("iis", $inspection_type_id, $genba_id, $date);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $comment_row = $result->fetch_assoc();
                            $comment_value = $comment_row['comments'] ?? null;

                            $stmt->close();
                            return $comment_value;
                        }

                        /**
                         * 指定された日付、現場ID、点検種類IDに対応する検査者IDを取得します。
                         *
                         * @param object $conn データベース接続オブジェクト
                         * @param int $inspection_type_id 点検種類ID
                         * @param int $genba_id 現場ID
                         * @param string $date 日付 (YYYY-MM-DD形式)
                         * @return int|null 検査者ID。該当データがない場合はnullを返します。
                         */
                        function getCheckerId($conn, $inspection_type_id, $genba_id, $date)
                        {
                            $checker_sql = "
                            SELECT checker_id
                            FROM inspections
                            WHERE inspection_type_id = ? 
                            AND genba_id = ? 
                            AND date = ?";

                            $stmt = $conn->prepare($checker_sql);
                            $stmt->bind_param("iis", $inspection_type_id, $genba_id, $date);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $checker_id = null;

                            if ($result->num_rows > 0) {
                                $checker_row = $result->fetch_assoc();
                                $checker_id = $checker_row['checker_id'] ?? null;
                            }

                            $stmt->close();
                            return $checker_id;
                        }
                        ?>
                        <thead>
                            <tr>
                                <?php if ($filter_applied) {
                                    $selected_month = $_GET['month'] ?? date('Y-m');
                                    $last_day = date('t', strtotime($selected_month));
                                    echo "<th class='text-center'>点検項目</th>";

                                    for ($day = 1; $day <= $last_day; $day++) {
                                        // 指定された月の$day日の日付をYYYY-MM-DD形式で取得
                                        $current_date = date('Y-m-d', strtotime("$selected_month-$day"));
                                        $day_of_week = date('w', strtotime($current_date)); // 曜日(0=日曜日, 6=土曜日)
                                        $checker_id = getCheckerId($conn, $inspection_type_id, $genba_id, $current_date);

                                        // 曜日に応じたクラスを設定
                                        $column_class = '';
                                        if ($day_of_week == 6) { // 土曜日
                                            $column_class = 'saturday-column';
                                        } elseif ($day_of_week == 0) { // 日曜日
                                            $column_class = 'sunday-column';
                                        } else {
                                            $column_class = 'table-light'; // 平日の背景
                                        }

                                        if ($inspection_type_id == 18 || $inspection_type_id == 30) {
                                            echo "<th class='text-center $column_class'>{$day}</th>";
                                        } else {
                                            echo "<th class='text-center $column_class'>
                                        <a href='#' onclick=\"openInspectionForm({$inspection_id}, {$genba_id}, {$inspection_type_id}, '{$current_date}', {$checker_id})\">{$day}</a>
                                        
                                        </th>";
                                        }
                                    }
                                } ?>
                            </tr>
                            <tr>
                                <?php if ($filter_applied) {
                                    echo "<th class='text-center'>曜日</th>";

                                    for ($day = 1; $day <= $last_day; $day++) {
                                        $current_date = date('Y-m-d', strtotime("$selected_month-$day"));
                                        $day_of_week = date('w', strtotime($current_date)); // 曜日(0=日曜日, 6=土曜日)
                                        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
                                        $day_name = $weekdays[$day_of_week];

                                        // 曜日に応じたクラスを設定
                                        $column_class = '';
                                        if ($day_of_week == 6) { // 土曜日
                                            $column_class = 'saturday-column';
                                        } elseif ($day_of_week == 0) { // 日曜日
                                            $column_class = 'sunday-column';
                                        } else {
                                            $column_class = 'table-light'; // 平日の背景
                                        }

                                        echo "<th class='text-center $column_class'>{$day_name}</th>";
                                    }
                                } ?>
                            </tr>
                        </thead>
                        <?php if ($filter_applied && !empty($inspection_items)): ?>
                            <?php
                            $inspection_item_names = [];
                            if ($filter_applied) {
                                // 点検項目をSQLから取得
                                $items_sql = "SELECT DISTINCT inspection_item_name, date FROM inspections WHERE inspection_type_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?";
                                $stmt = $conn->prepare($items_sql);
                                $stmt->bind_param("is", $inspection_type_id, $month); // パラメータに月を追加
                                $stmt->execute();
                                $result_items = $stmt->get_result();

                                // データベースの結果を直接加工してアイテム名をキーに
                                while ($item = $result_items->fetch_assoc()) {
                                    $day = (int)substr($item['date'], -2); // 日付文字列から "日" を取得
                                    $inspection_item_names[$item['inspection_item_name']][] = $day; // アイテム名をキーとして格納
                                }
                                $stmt->close();
                            }

                            // 点検項目ごとのデータ生成
                            $item_rows = '';
                            if ($inspection_type_id == 18 || $inspection_type_id == 30) {
                                // $inspection_item_names: 点検項目名と、その点検が行われた日(日付の配列)の連想配列
                                foreach ($inspection_item_names as $item_name => $days) {
                                    // 各点検項目の行HTMLを生成
                                    $row_html = "<tr class='text-center'>";
                                    // 点検項目名のセルを追加。テキストラップと幅を指定
                                    $row_html .= "<td class=\"text-start text-wrap\" style=\"width: 10rem;\">" . sanitizeInput($item_name) . "</td>";

                                    // 各日のセルを追加
                                    for ($day = 1; $day <= $last_day; $day++) {
                                        // 当日の日付を生成
                                        $current_date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                        // 曜日を取得
                                        $day_of_week = date('w', strtotime($current_date));
                                        // 曜日によるCSSクラスを設定
                                        $column_class = $day_of_week == 6 ? 'saturday-column' : ($day_of_week == 0 ? 'sunday-column' : '');
                                        // 点検が行われた日であれば〇マークを表示、そうでなければ空文字
                                        $mark = in_array($day, $days) ? "<a href='#' data-bs-toggle='modal' data-bs-target='#inspectionModal' data-date='{$current_date}' data-item-name='" . sanitizeInput($item_name) . "' data-bs-dismiss='modal'>〇</a>" : '';
                                        // セルを追加
                                        $row_html .= "<td class=\"{$column_class}\">{$mark}</td>";
                                    }
                                    // 行HTMLを閉じ、$item_rowsに追加
                                    $row_html .= "</tr>";
                                    $item_rows .= $row_html;
                                    /* if (!empty($inspection_item_names)):
                                        var_dump($inspection_item_names);
                                    endif; */
                                }
                            } else {
                                // 点検者行のデータ生成
                                $inspector_row = '';
                                for ($day = 1; $day <= $last_day; $day++) {
                                    // 当日の日付を生成します。
                                    $current_date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                    // 点検者名をデータベースから取得します。
                                    $inspector_name = getInspectorName($conn, $inspection_type_id, $genba_id, $current_date);
                                    // 点検者名の氏名部分のみを取得します。スペースで分割し、最初の要素を取得
                                    $inspector_name_h = explode(' ', $inspector_name)[0];
                                    // 曜日の数値を取得します。(0:日曜日, 1:月曜日, ..., 6:土曜日)
                                    $day_of_week = date('w', strtotime($current_date));
                                    // 曜日によってCSSクラスを決定します。土曜日ならsaturday-column、日曜日ならsunday-column、それ以外は空文字
                                    $column_class = $day_of_week == 6 ? 'saturday-column' : ($day_of_week == 0 ? 'sunday-column' : '');

                                    // 点検者名の氏名を表示するセルを生成します。ツールチップでフルネームを表示するように設定
                                    $inspector_row .= "<td class=\"{$column_class}\" data-bs-toggle=\"tooltip\" title=\"" . sanitizeInput($inspector_name) . "\" data-bs-placement=\"bottom\">" . sanitizeInput($inspector_name_h) . "</td>";
                                }
                                // 各点検項目ごとに1行のHTMLを生成するループ
                                foreach ($inspection_items as $item_id => $item_name) {
                                    // 各項目の行のHTMLを初期化
                                    $row_html = "<tr class='text-center'>";
                                    // 項目名をセルに追加
                                    $row_html .= "<td class=\"text-start text-wrap\" style=\"width: 10rem;\">" . sanitizeInput($item_name) . "</td>";
                                    // 各日についてセルを追加
                                    for ($day = 1; $day <= $last_day; $day++) {
                                        // 日付を生成
                                        $current_date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                        // 点検データを取得
                                        $data = $inspection_data[$current_date] ?? null;
                                        // 点検結果を取得。なければ空文字
                                        $result_value = $data['items'][$item_id] ?? '';
                                        // 点検IDを取得。なければnull
                                        $inspection_id = $data['inspection_id'] ?? null;
                                        // 曜日を取得
                                        $day_of_week = date('w', strtotime($current_date));
                                        // 曜日によるCSSクラスを設定
                                        $column_class = $day_of_week == 6 ? 'saturday-column' : ($day_of_week == 0 ? 'sunday-column' : '');
                                        // セルを生成。編集モーダルを開くためのデータ属性を追加
                                        $row_html .= "<td class=\"text-center editable px-1 align-middle {$column_class}\" data-bs-toggle=\"modal\" data-bs-target=\"#editModal\" 
                                  data-inspection_id=\"{$inspection_id}\" data-item_id=\"{$item_id}\" 
                                  data-result_value=\"" . sanitizeInput($result_value) . "\" data-date=\"" . sanitizeInput($current_date) . "\">{$result_value}</td>";
                                    }
                                    // 行のHTMLを閉じ、$item_rowsに追加
                                    $row_html .= "</tr>";
                                    $item_rows .= $row_html;
                                }
                            }

                            // 備考行のデータ生成
                            $comment_row = '';
                            if ($inspection_type_id != 18 && $inspection_type_id != 30) {
                                for ($day = 1; $day <= $last_day; $day++) {
                                    $current_date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                    $comment_value = getComment($conn, $inspection_type_id, $genba_id, $current_date);
                                    $day_of_week = date('w', strtotime($current_date));
                                    $column_class = $day_of_week == 6 ? 'saturday-column' : ($day_of_week == 0 ? 'sunday-column' : '');

                                    $comment_row .= "<td class=\"{$column_class}\" data-bs-toggle=\"tooltip\" data-bs-placement=\"bottom\" 
                              title=\"" . addslashes(sanitizeInput($comment_value)) . "\">" . (!empty($comment_value) ? '有' : '') . "</td>";
                                }
                            }
                            ?>
                        <?php endif; ?>
                        <tbody>
                            <?php if (isset($inspector_row) && !empty($inspector_row)): ?>
                                <!-- 点検者行 -->
                                <tr>
                                    <td class="text-start">点検者</td>
                                    <?= $inspector_row ?>
                                </tr>
                            <?php endif; ?>

                            <?php if (isset($item_rows) && !empty($item_rows)): ?>
                                <!-- 点検項目ごとの行 -->
                                <?= $item_rows ?>
                            <?php endif; ?>

                            <?php if (isset($comment_row) && !empty($comment_row)): ?>
                                <!-- 備考行 -->
                                <tr>
                                    <td class="text-start">備考</td>
                                    <?= $comment_row ?>
                                </tr>
                            <?php endif; ?>
                        </tbody>


                    </table>

                </div>
                <button id="printButton" class="btn btn-secondary mb-3" onclick="printTable()" style="display: none;">テーブルを印刷</button>
            </div>
            <!-- モーダルウィンドウ -->
            <div class="modal fade" id="inspectionModal" tabindex="-1" aria-labelledby="inspectionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="inspectionModalLabel">点検データ編集</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <iframe id="inspectionFormFrame" src="" width="100%" height="500px" frameborder="0"></iframe>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
    <div id="footer">
        <?php include 'footer.php'; ?>
    </div>

    <script>
        $(document).ready(function() {
            // 現場名または対象月が変更されたときに点検種類を再読み込み
            $('#genba_id, #month').change(function() {
                var genba_id = $('#genba_id').val();
                var month = $('#month').val();
                // 初期状態でボタンを非表示にする
                $('#printButton').hide();
                // 月が変更されたときに現場名のドロップダウンの最初の選択肢を選択
                if ($(this).attr('id') === 'month') {
                    $('#genba_id option:first').prop('selected', true); // 現場名のドロップダウンの最初の選択肢を選択
                    $('#inspection_type_id').empty().append('<option value="" disabled selected>選択してください</option>');
                }

                // レコード表示部分を初期化
                $('.table-responsive').empty(); // <div class="table-responsive">の内容をクリア

                // AJAXリクエストを送信して点検種類を取得
                $.ajax({
                    url: 'get_inspection_types.php',
                    method: 'GET',
                    data: {
                        genba_id: genba_id,
                        month: month
                    },
                    success: function(response) {
                        $('#inspection_type_id').html(response); // 点検種類ドロップダウンを更新
                    }
                });
            });
            $('#genba_id').on('change', function() {
                // 現場名が初期値の場合
                if ($(this).val() === '') {
                    // 点検タイプのドロップダウンをリセット
                    $('#inspection_type_id').empty().append('<option value="" disabled selected>選択してください</option>');
                }
            });
            // 点検結果が表示されたときにボタンを表示
            function showPrintButton() {
                if ($('.table-responsive').children().length > 0) {
                    $('#printButton').show(); // テーブルが表示されている場合はボタンを表示
                } else {
                    $('#printButton').hide(); // テーブルが表示されていない場合はボタンを非表示
                }
            }

            // 点検種類が変更されたときにフォームを自動送信
            $('#inspection_type_id').change(function() {
                $('#filter-form').submit();
            });

            // フォーム送信後にテーブルが表示されたらボタンを表示
            $('#filter-form').on('submit', function() {
                // ここでテーブルが表示される処理を行った後にボタンを表示
                showPrintButton();
            });

            // ページ読み込み時にテーブルの状態を確認
            showPrintButton(); // 初期状態を確認
        });

        function updateRecord(genbaId, inspectionTypeId, date) {
            $.ajax({
                url: 'get_updated_record.php', // 更新されたデータを取得するためのエンドポイント
                method: 'GET',
                data: {
                    genba_id: genbaId,
                    inspection_type_id: inspectionTypeId,
                    date: date
                },
                success: function(response) {
                    // 取得したデータでページの一部を更新
                    $('#recordContainer').html(response);
                },
                error: function(xhr, status, error) {
                    console.error('データの取得に失敗しました:', error);
                }
            });
        }
        $(document).ready(function() {
            $('#inspectionModal').on('hidden.bs.modal', function() {
                // モーダルが閉じられたときにAJAXを呼び出す
                const genbaId = $('#genba_id').val();
                const inspectionTypeId = $('#inspection_type_id').val();
                const date = $('#date').val();
                updateRecord(genbaId, inspectionTypeId, date);
            });

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
        document.addEventListener("DOMContentLoaded", function() {
            // ツールチップを初期化
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // タップイベントでツールチップを表示・非表示
            tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                tooltipTriggerEl.addEventListener("click", function() {
                    var tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                    if (tooltip._element.getAttribute("aria-describedby")) {
                        tooltip.hide(); // ツールチップが表示されている場合は隠す
                    } else {
                        tooltip.show(); // ツールチップが非表示の場合は表示する
                    }
                });
            });
        });

        /* function openInspectionForm(genbaId, inspectionTypeId, date, checkerId) {
            console.log("Checker ID:", checkerId); // デバッグ用
            const url = `edit_record.php?genba_id=${genbaId}&inspection_type=${inspectionTypeId}&date=${date}&checker_id=${checkerId}`;
            document.getElementById('inspectionFormFrame').src = url;
            const modal = new bootstrap.Modal(document.getElementById('inspectionModal'));
            modal.show();
        } */

        function submitInspectionForm() {
            const iframe = document.getElementById('inspectionFormFrame');
            iframe.contentWindow.document.querySelector('form').submit();
            const modal = bootstrap.Modal.getInstance(document.getElementById('inspectionModal'));
            modal.hide();
        }

        function openInspectionForm(inspection_id, genbaId, inspectionTypeId, date, checkerId) {
            console.log("genbaId:", genbaId);
            console.log("inspectionTypeId:", inspectionTypeId);
            console.log("date:", date);
            console.log("checkerId:", checkerId);
            // クリックした日付の結果を取得

            <?php if (!empty($inspection_data)): ?>
                var inspectionResults = <?php echo json_encode($inspection_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            <?php else: ?>
                var inspectionResults = []; // 空の配列を設定
            <?php endif; ?>

            <?php if (!empty($comments)): ?>
                var inspectionComments = <?php echo json_encode($comments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            <?php else: ?>
                var inspectionComments = ""; // 空の配列を設定
            <?php endif; ?>

            // console.log(inspectionResults[date]);
            const resultValues = inspectionResults[inspection_id] || [];
            const encodedResults = encodeURIComponent(JSON.stringify(resultValues));
            // console.log("Result Values:", resultValues); // デバッグ用
            const commentValue = inspectionComments[inspection_id] || '';
            const encodedComment = encodeURIComponent(commentValue);

            console.log("URL:", `edit_record.php?genba_id=${genbaId}&inspection_type=${inspectionTypeId}&date=${date}&checker_id=${checkerId}&result_values=${encodedResults}&comment=${encodedComment}`); // 構築されたURLを出力して確認

            const url = `edit_record.php?inspection_id=${inspection_id}genba_id=${genbaId}&inspection_type=${inspectionTypeId}&date=${date}&checker_id=${checkerId}&result_values=${encodedResults}&comment=${encodedComment}`;
            document.getElementById('inspectionFormFrame').src = url;
            const modal = new bootstrap.Modal(document.getElementById('inspectionModal'));
            modal.show();
        }
        // iframe からのメッセージを受け取る
        window.addEventListener('message', function(event) {
            if (event.data.status === 'session_expired') {
                // メッセージを表示（オプション）
                alert(event.data.message);

                // モーダルを閉じる
                $('#inspectionModal').modal('hide');

                // ログインページにリダイレクト
                window.location.href = 'index.php'; // ログイントップのURL
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>

</html>