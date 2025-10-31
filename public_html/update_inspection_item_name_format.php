<?php
/**
 * inspectionsテーブルのinspection_item_nameカラムを正規化
 * 英数字→半角、カナ→全角に統一
 */

// CLIモードまたはSERVER_ADDRが未設定の場合、localhostとして設定
if (!isset($_SERVER['SERVER_ADDR']) || php_sapi_name() === 'cli') {
    $_SERVER['SERVER_ADDR'] = 'localhost';
}

// ブラウザからのアクセスの場合、認証チェック（オプション）
if (php_sapi_name() !== 'cli') {
    // 簡易的なセキュリティ: 実行確認パラメータを要求
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        die('このスクリプトを実行するには ?confirm=yes パラメータが必要です。');
    }
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>inspection_item_name正規化</title></head><body><pre>";
}

require_once dirname(__DIR__) . '/private/config.php';
require_once dirname(__DIR__) . '/private/functions.php';

$conn = connectDB();

echo "=== inspections テーブル inspection_item_name 正規化処理開始 ===\n\n";

$result = $conn->query("SELECT id, inspection_item_name FROM inspections WHERE inspection_item_name IS NOT NULL AND inspection_item_name != ''");
if (!$result) {
    die("Query failed: " . $conn->error);
}

$updated = 0;
$unchanged = 0;

while ($row = $result->fetch_assoc()) {
    $originalName = $row['inspection_item_name'];
    // 英数字を半角、カナを全角、スペースを半角、濁点結合
    $newName = mb_convert_kana($originalName, 'asKV', 'UTF-8');

    if ($newName !== $originalName) {
        $stmt = $conn->prepare("UPDATE inspections SET inspection_item_name = ? WHERE id = ?");
        $stmt->bind_param('si', $newName, $row['id']);

        if ($stmt->execute()) {
            echo "✓ Updated ID {$row['id']}:\n";
            echo "  Before: '{$originalName}'\n";
            echo "  After:  '{$newName}'\n\n";
            $updated++;
        } else {
            echo "✗ Failed to update ID {$row['id']}: " . $stmt->error . "\n\n";
        }
        $stmt->close();
    } else {
        $unchanged++;
    }
}

echo "=== 処理完了 ===\n";
echo "更新: {$updated} 件\n";
echo "変更なし: {$unchanged} 件\n";
echo "合計: " . ($updated + $unchanged) . " 件\n";

$conn->close();

// ブラウザからのアクセスの場合、HTMLを閉じる
if (php_sapi_name() !== 'cli') {
    echo "</pre></body></html>";
}
?>
