# 本番環境デプロイファイルリスト - 2025/10/24

## 📋 デプロイ対象ファイル

### ✅ 必須ファイル（本日の修正）

#### 1. 点検済みグレーアウト機能関連
```
public_html/get_staffing.php
public_html/submit_inspection.php
public_html/inspection_top.php
```

**説明**:
- `get_staffing.php` - check_car_item.php呼び出し削除（500エラー解消）
- `submit_inspection.php` - smart_assignments更新処理実装
- `inspection_top.php` - 日付選択に対応した点検済み判定

#### 2. 関連機能ファイル
```
private/functions.php
public_html/get_genba_list.php
```

**説明**:
- `functions.php` - getFilteredData()、getAssignmentsForInspection()関数
- `get_genba_list.php` - 日付ベース現場リスト取得API

---

## 🔧 デプロイ手順

### 方法1: Gitでデプロイ（推奨）

```bash
# 本番サーバーにSSH接続
ssh user@check.fujimura-gumi.com

# プロジェクトディレクトリに移動
cd /path/to/check.fujimura-gumi.com

# 最新のコミットをpull
git pull origin main
```

### 方法2: FTPでデプロイ

以下のファイルを本番サーバーにアップロード：

**アップロード先**: `check.fujimura-gumi.com`

```
/private/functions.php
/public_html/get_staffing.php
/public_html/submit_inspection.php
/public_html/inspection_top.php
/public_html/get_genba_list.php
```

---

## ⚠️ デプロイ前の確認事項

### 1. ローカルでのテスト
- [ ] get_staffing.phpで点検済みボタンがグレーアウトされる
- [ ] inspection_top.phpで日付選択が正しく機能する
- [ ] 点検送信後、smart_assignmentsが更新される

### 2. 本番環境の確認
- [ ] データベースにsmart_assignmentsテーブルが存在する
- [ ] inspection_completedカラムが存在する
- [ ] target_nameテーブルが存在する（inspection_top.php用）

### 3. バックアップ
- [ ] 本番環境の既存ファイルをバックアップ
- [ ] データベースのバックアップ（念のため）

---

## 📝 デプロイ後の確認項目

### 1. get_staffing.php
- [ ] ページが正常に表示される
- [ ] 重機ボタンクリック時に500エラーが出ない
- [ ] 点検送信後、ボタンがグレーアウトされる

### 2. inspection_top.php
- [ ] 日付選択が正常に動作する
- [ ] 選択した日付の点検済みボタンがグレーアウトされる
- [ ] 現場選択で現場リストが正しく表示される

### 3. submit_inspection.php
- [ ] 点検送信が正常に完了する
- [ ] smart_assignmentsテーブルのinspection_completedが1になる
- [ ] エラーログに警告が出ていないか確認

**エラーログ確認コマンド**:
```bash
tail -f /var/log/apache2/error.log | grep "smart_assignments"
```

---

## 🔍 関連するコミット

### 最新3つのコミット
```
0acec61 - 選択された日付に基づく点検履歴の取得機能を実装
08209a3 - check_car_item.php呼び出しを削除
948a99c - 現場データ取得機能の改善と新規API追加
```

### コミット詳細

#### コミット1: inspection_top.php日付選択対応
```
コミットID: 0acec61
ファイル: public_html/inspection_top.php
変更内容: $todayをselected_dateに変更、日付選択対応
```

#### コミット2: check_car_item.php呼び出し削除
```
コミットID: 08209a3
ファイル: public_html/get_staffing.php
変更内容: 不要なAPI呼び出し削除（71行削減）
```

#### コミット3: 現場データ取得機能改善
```
コミットID: 948a99c
ファイル:
  - private/functions.php
  - public_html/get_genba_list.php
  - public_html/inspection_top.php
変更内容: smart_assignmentsベースの現場取得機能
```

---

## 🚨 トラブルシューティング

### 問題1: 点検済みボタンがグレーアウトされない
**確認事項**:
1. smart_assignmentsテーブルにinspection_completed=1のレコードがあるか
2. submit_inspection.phpが最新版か
3. target_name_idが正しく送信されているか

**解決方法**:
```sql
-- データ確認
SELECT * FROM smart_assignments
WHERE inspection_completed = 1
ORDER BY updated_at DESC LIMIT 10;
```

### 問題2: 500エラーが出る
**確認事項**:
1. functions.phpが正しくアップロードされているか
2. パスが正しいか（private/functions.php）
3. PHPのエラーログを確認

**解決方法**:
```bash
# エラーログ確認
tail -100 /var/log/apache2/error.log
```

### 問題3: 現場リストが表示されない
**確認事項**:
1. get_genba_list.phpが存在するか
2. smart_assignmentsテーブルにデータがあるか
3. JavaScriptコンソールにエラーが出ていないか

**解決方法**:
ブラウザのDevTools > Console でエラー確認

---

## 📞 サポート

デプロイ中に問題が発生した場合：
1. エラーログを確認
2. ブラウザのDevToolsでエラー確認
3. データベースの状態を確認

必要に応じてロールバック可能：
```bash
git log --oneline  # コミット履歴確認
git reset --hard [前のコミットID]  # ロールバック
```
