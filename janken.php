<?php
// 手の定義 (キー => 表示名)
$hands = [
    'goo'   => '✊ グー',
    'choki' => '✌️ チョキ',
    'paa'   => '✋ パー'
];

// 変数の初期化
$playerHand = '';
$pcHand = '';
$result = '';
$showResult = false;

// POSTリクエスト（ボタンが押された）場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['player_hand'])) {
    $playerHand = $_POST['player_hand'];

    // 不正な入力チェック
    if (array_key_exists($playerHand, $hands)) {
        $showResult = true;

        // コンピュータの手をランダムに決定 (array_randはキーを返す)
        $pcHand = array_rand($hands);

        // 勝敗判定ロジック
        if ($playerHand === $pcHand) {
            $result = 'あいこ';
            $resultColor = 'gray'; // 引き分けの色
        } elseif (
            ($playerHand === 'goo' && $pcHand === 'choki') ||
            ($playerHand === 'choki' && $pcHand === 'paa') ||
            ($playerHand === 'paa' && $pcHand === 'goo')
        ) {
            $result = 'あなたの勝ち！';
            $resultColor = '#e74c3c'; // 勝ちの色 (赤)
        } else {
            $result = 'あなたの負け...';
            $resultColor = '#3498db'; // 負けの色 (青)
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP じゃんけんゲーム</title>
    <style>
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            text-align: center;
            background-color: #f0f2f5;
            color: #333;
            padding-top: 50px;
        }
        .container {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1 { margin-bottom: 30px; font-size: 24px; }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        button {
            font-size: 18px;
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            transition: 0.3s;
        }
        button:hover { opacity: 0.8; transform: scale(1.05); }
        .btn-goo { background-color: #e67e22; color: white; }
        .btn-choki { background-color: #27ae60; color: white; }
        .btn-paa { background-color: #8e44ad; color: white; }
        
        .result-box {
            margin-top: 30px;
            padding: 20px;
            border-top: 2px solid #eee;
        }
        .hand-display {
            font-size: 20px;
            margin: 10px 0;
        }
        .outcome {
            font-size: 32px;
            font-weight: bold;
            margin-top: 15px;
        }
        .reset-link {
            display: inline-block;
            margin-top: 20px;
            color: #7f8c8d;
            text-decoration: none;
        }
        .reset-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h1>じゃんけんゲーム</h1>

    <form method="post" action="">
        <div class="btn-group">
            <button type="submit" name="player_hand" value="goo" class="btn-goo">✊ グー</button>
            <button type="submit" name="player_hand" value="choki" class="btn-choki">✌️ チョキ</button>
            <button type="submit" name="player_hand" value="paa" class="btn-paa">✋ パー</button>
        </div>
    </form>

    <?php if ($showResult): ?>
        <div class="result-box">
            <div class="hand-display">
                あなた： <strong><?php echo htmlspecialchars($hands[$playerHand]); ?></strong>
            </div>
            <div class="hand-display">
                相手　： <strong><?php echo htmlspecialchars($hands[$pcHand]); ?></strong>
            </div>
            
            <div class="outcome" style="color: <?php echo $resultColor; ?>">
                <?php echo $result; ?>
            </div>
        </div>
        
        <a href="janken.php" class="reset-link">もう一度遊ぶ（リセット）</a>
    <?php else: ?>
        <p>手を選んでください！</p>
    <?php endif; ?>

</div>

</body>
</html>
