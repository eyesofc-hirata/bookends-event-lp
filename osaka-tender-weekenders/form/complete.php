<?php
/**
 * 完了画面 / Completion (thank-you) screen
 * 確認画面からの POST を検証し、メール送信のうえサンキュー画面を表示する。
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';

// 直接アクセスはフォームへ戻す
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_to_form();
}

// CSRF / 二重送信チェック（確認画面で発行したワンタイムトークン）
$token = post('_token');
if ($token === '' || !csrf_verify($token)) {
    render_head('セッションエラー');
    echo '<div class="fp-label">RESERVATION</div>';
    echo '<h1 class="fp-title">送信を完了できませんでした</h1>';
    echo '<p class="fp-lead">ページの有効期限が切れたか、すでに送信済みの可能性があります。<br>お手数ですが、もう一度フォームからお試しください。</p>';
    echo '<div class="fp-actions"><a class="fp-btn fp-btn--primary" href="../index.html#reserve">フォームへ戻る</a></div>';
    render_foot();
    exit;
}
// 一度使ったトークンは破棄（リロードによる二重送信を防止）
csrf_clear();

// サーバー側で再検証
[$d, $errors] = validate_input();
if ($errors) {
    render_head('入力内容をご確認ください');
    echo '<div class="fp-label">RESERVATION</div>';
    echo '<h1 class="fp-title">入力内容をご確認ください</h1>';
    echo '<ul class="fp-errors">';
    foreach ($errors as $e) {
        echo '<li>' . h($e) . '</li>';
    }
    echo '</ul>';
    echo '<div class="fp-actions"><a class="fp-btn fp-btn--primary" href="../index.html#reserve">フォームへ戻る</a></div>';
    render_foot();
    exit;
}

// メール送信
$sent = send_reservation_mails($d);

render_head('ご予約ありがとうございます');
?>
<div class="thanks-mark" aria-hidden="true">
  <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M5 12.5l4.2 4.2L19 7" stroke-linecap="round" stroke-linejoin="round"></path>
  </svg>
</div>
<div class="fp-label">RESERVATION</div>
<h1 class="fp-title">ご予約ありがとうございます</h1>
<p class="fp-lead">
  ご予約を受け付けました。<br>
  ご入力のメールアドレスへ確認メールをお送りしています。<br>
  <strong style="color:var(--accent);font-weight:500;">整理番号は、追って担当者よりご連絡いたします。</strong>
</p>

<?php if (!$sent): ?>
<ul class="fp-errors">
  <li>ご予約は受け付けましたが、確認メールの送信に問題が発生した可能性があります。お手数ですが <?= h($CONFIG['admin_email']) ?> までご連絡ください。</li>
</ul>
<?php endif; ?>

<p class="thanks-note">
  しばらく経っても確認メールが届かない場合は、迷惑メールフォルダをご確認のうえ、<?= h($CONFIG['admin_email']) ?> までお問い合わせください。
</p>
<div class="fp-actions" style="margin-top:30px;">
  <a class="fp-btn fp-btn--ghost" href="../index.html">トップへ戻る</a>
</div>
<?php
render_foot();
