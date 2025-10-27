<?php
// 正規化テスト
$testString = "有島護岸\n（R7）"; // sortableテーブルから取得される値

echo "元の文字列: " . var_export($testString, true) . "\n";
echo "16進ダンプ: " . bin2hex($testString) . "\n\n";

// ステップ1: 空白・改行削除
$step1 = str_replace([" ", "\n", "\r", "\t"], "", $testString);
echo "ステップ1 (空白削除): " . var_export($step1, true) . "\n";
echo "16進ダンプ: " . bin2hex($step1) . "\n\n";

// ステップ2: mb_convert_kana with "askh"
$step2 = mb_convert_kana($step1, "askh", "UTF-8");
echo "ステップ2 (askh変換): " . var_export($step2, true) . "\n";
echo "16進ダンプ: " . bin2hex($step2) . "\n\n";

// JavaScript側の処理結果（期待値）
$expected = "有島護岸(R7)";
echo "JavaScript期待値: " . var_export($expected, true) . "\n";
echo "16進ダンプ: " . bin2hex($expected) . "\n\n";

// 一致確認
echo "一致: " . ($step2 === $expected ? "✓ YES" : "✗ NO") . "\n";
?>
