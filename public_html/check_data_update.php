<?php
/**
 * smart_assignmentsの更新チェック
 * get_staffing.phpからポーリングで呼ばれる
 * 指定日付の全データをチェックし、更新があればページをリロード
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../fujimura-gumi.com/private/config.php';

try {
    $date = $_POST['date'] ?? null;

    if (!$date) {
        echo json_encode([
            'success' => false,
            'error' => 'date is required'
        ]);
        exit;
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // 指定日付の全データの最終更新日時を取得
    $sql = "SELECT MAX(updated_at) as last_update, COUNT(*) as count
            FROM smart_assignments
            WHERE assignment_date = :date";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date' => $date]);

    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'last_update' => $result['last_update'],
        'count' => (int)$result['count'],
        'date' => $date
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
