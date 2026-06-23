<?php
/**
 * 確認画面 / Confirmation screen
 * 入力フォーム(index.html) からの POST を検証し、確認内容を表示する。
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';

// 直接アクセス（GET等）はフォームへ戻す
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_to_form();
}

// ハニーポット（ボット対策）：値が入っていれば送信を黙って破棄
if (post('website') !== '') {
    redirect_to_form();
}

[$d, $errors] = validate_input();

// 検証エラー：エラー画面を表示（ブラウザの戻るで入力値は保持される）
if ($errors) {
    render_head('入力内容をご確認ください');
    echo '<div class="fp-label">RESERVATION</div>';
    echo '<h1 class="fp-title">入力内容をご確認ください</h1>';
    echo '<ul class="fp-errors">';
    foreach ($errors as $e) {
        echo '<li>' . h($e) . '</li>';
    }
    echo '</ul>';
    echo '<div class="fp-actions">';
    echo '<a class="fp-btn fp-btn--ghost" href="javascript:history.back()">戻って修正する</a>';
    echo '</div>';
    render_foot();
    exit;
}

// 検証OK：確認内容 + 送信トークンを表示
$token = csrf_issue();

render_head('ご予約内容の確認');
?>
<div class="fp-label">RESERVATION</div>
<h1 class="fp-title">ご予約内容の確認</h1>
<p class="fp-lead">以下の内容でお間違いなければ、<br>「この内容で送信する」を押してください。</p>

<div class="confirm-list">
  <div class="confirm-row"><div class="confirm-key">お名前</div><div class="confirm-val"><?= h($d['name']) ?></div></div>
  <div class="confirm-row"><div class="confirm-key">ふりがな</div><div class="confirm-val"><?= h($d['kana']) ?></div></div>
  <div class="confirm-row"><div class="confirm-key">枚数</div><div class="confirm-val"><?= h(qty_label($d['qty'])) ?></div></div>
  <div class="confirm-row"><div class="confirm-key">メール</div><div class="confirm-val"><?= h($d['email']) ?></div></div>
  <div class="confirm-row"><div class="confirm-key">電話番号</div><div class="confirm-val"><?= h($d['tel']) ?></div></div>
</div>

<form action="complete.php" method="post" class="fp-actions">
  <input type="hidden" name="_token" value="<?= h($token) ?>">
  <input type="hidden" name="name"  value="<?= h($d['name']) ?>">
  <input type="hidden" name="kana"  value="<?= h($d['kana']) ?>">
  <input type="hidden" name="qty"   value="<?= h($d['qty']) ?>">
  <input type="hidden" name="email" value="<?= h($d['email']) ?>">
  <input type="hidden" name="tel"   value="<?= h($d['tel']) ?>">
  <button type="submit" class="fp-btn fp-btn--primary">この内容で送信する</button>
  <a class="fp-btn fp-btn--ghost" href="javascript:history.back()">修正する</a>
</form>
<?php
render_foot();
