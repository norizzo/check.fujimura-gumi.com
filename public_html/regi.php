<?php
require_once 'db_connection.php'; // データベース接続スクリプトを読み込む

// 登録するユーザー情報の配列
$users = [
    ['username' => '田中利憲', 'password' => 'ttanaka2400','display'=>'田中 利憲'],
    ['username' => '本山塁', 'password' => 'rmotoyama2400','display'=>'本山 塁'],
    ['username' => '山本一弘', 'password' => 'kyamamoto2400','display'=>'山本 一弘'],
    ['username' => '秋山惇', 'password' => 'jakiyama2400','display'=>'秋山 惇'],
    ['username' => '秋山剛', 'password' => 'takiyama2400','display'=>'秋山 剛'],
    ['username' => '金井浩幸', 'password' => 'hkanai2400','display'=>'金井 浩幸'],
    ['username' => '小島聡明', 'password' => 'tkojima2400','display'=>'小島 聡明'],
    ['username' => '小山哲郎', 'password' => 'tkoyama2400','display'=>'小山 哲郎'],
    ['username' => '杉本義夫', 'password' => 'ysugimoto2400','display'=>'杉本 義夫'],
    ['username' => '丸田誠一', 'password' => 'smaruta2400','display'=>'丸田 誠一'],
    ['username' => '横田輝雄', 'password' => 'tyokota2400','display'=>'横田 輝雄'],
    ['username' => '渡邉賢太郎', 'password' => 'kwatanabe2400','display'=>'渡邉 賢太郎'],
    ['username' => '橋爪直広', 'password' => 'nhashidume2400','display'=>'橋爪 直広'],
    ['username' => '星野篤', 'password' => 'ahoshino2400','display'=>'星野 篤'],
    ['username' => '藤村行夫', 'password' => 'yfujimura2400','display'=>'藤村 行夫'],
    ['username' => '吉澤周之輔', 'password' => 'syoshizawa2400','display'=>'吉澤 周之輔'],
    ['username' => '和栗幸', 'password' => 'mwaguri2400','display'=>'和栗 幸'],
    ['username' => '竹田ひろみ', 'password' => 'htakeda2400','display'=>'竹田 ひろみ'],
    ['username' => '澤田颯希', 'password' => 'ssawada2400','display'=>'澤田 颯希'],
    ['username' => '和栗奈々', 'password' => 'nwaguri2400','display'=>'和栗 奈々'],
    ['username' => '保坂美希', 'password' => 'mhosaka2400','display'=>'保坂 美希'],
    ['username' => '江村和代', 'password' => 'kemura2400','display'=>'江村 和代'],
    ['username' => '小山美栄', 'password' => 'mkoyama2400','display'=>'小山 美栄'],
];

try {
    // トランザクションを開始
    $pdo->beginTransaction();

    // ユーザー情報をDBに登録する準備
    $stmt = $pdo->prepare("INSERT INTO users (username, password,display) VALUES (:username, :password,:display)");

    foreach ($users as $user) {
        // パスワードをハッシュ化
        $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);

        // バインドパラメータに値をセット
        $stmt->bindParam(':username', $user['username']);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':display', $user['display']);

        // SQLを実行
        $stmt->execute();
    }

    // トランザクションをコミット
    $pdo->commit();

    echo "全てのユーザー情報が正常に登録されました。";
} catch (PDOException $e) {
    // エラーが発生した場合はロールバック
    $pdo->rollBack();
    echo "登録中にエラーが発生しました: " . $e->getMessage();
}
?>

