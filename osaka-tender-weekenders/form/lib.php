<?php
/**
 * 予約フォーム共通処理 / shared helpers
 */
declare(strict_types=1);

mb_language('Japanese');
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$CONFIG = require __DIR__ . '/config.php';

require_once __DIR__ . '/smtp.php';

/** フォーム項目のラベル */
const FIELD_LABELS = [
    'name'  => 'お名前',
    'kana'  => 'ふりがな',
    'qty'   => '枚数',
    'email' => 'メールアドレス',
    'tel'   => '電話番号',
];

/** HTMLエスケープ */
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** POST値の取得（前後空白除去） */
function post(string $key): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return trim($v);
}

/** CSRFトークン発行（確認画面で発行 → 完了画面で検証） */
function csrf_issue(): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['reserve_csrf'] = $token;
    return $token;
}

/** CSRFトークン検証（ワンタイム） */
function csrf_verify(string $token): bool
{
    $ok = isset($_SESSION['reserve_csrf'])
        && is_string($_SESSION['reserve_csrf'])
        && hash_equals($_SESSION['reserve_csrf'], $token);
    return $ok;
}

/** CSRFトークン破棄（送信完了後の二重送信防止） */
function csrf_clear(): void
{
    unset($_SESSION['reserve_csrf']);
}

/**
 * 入力値の検証
 * @return array{0: array<string,string>, 1: array<int,string>} [clean, errors]
 */
function validate_input(): array
{
    $clean = [
        'name'  => post('name'),
        'kana'  => post('kana'),
        'qty'   => post('qty'),
        'email' => post('email'),
        'tel'   => post('tel'),
    ];
    $errors = [];

    if ($clean['name'] === '') {
        $errors[] = 'お名前をご入力ください。';
    } elseif (mb_strlen($clean['name']) > 100) {
        $errors[] = 'お名前が長すぎます。';
    }

    if ($clean['kana'] === '') {
        $errors[] = 'ふりがなをご入力ください。';
    } elseif (!preg_match('/^[\p{Hiragana}\p{Katakana}ー\x{30FC}\s　]+$/u', $clean['kana'])) {
        $errors[] = 'ふりがなはひらがな・カタカナでご入力ください。';
    }

    if (!preg_match('/^[1-4]$/', $clean['qty'])) {
        $errors[] = '枚数を 1〜4 の範囲でお選びください。';
    }

    if ($clean['email'] === '' || !filter_var($clean['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($clean['email']) > 255) {
        $errors[] = 'メールアドレスを正しくご入力ください。';
    }

    // メールヘッダインジェクション対策（改行混入を拒否）
    if (preg_match('/[\r\n]/', $clean['email'])) {
        $errors[] = 'メールアドレスに使用できない文字が含まれています。';
    }

    if (!preg_match('/^[0-9\-+\s()]{10,20}$/', $clean['tel'])) {
        $errors[] = '電話番号を正しくご入力ください（数字・ハイフン）。';
    }

    return [$clean, $errors];
}

/** 枚数の表示用 */
function qty_label(string $qty): string
{
    return $qty . ' 枚';
}

/**
 * メール1通を送信（設定の mail_method に応じて mail() / SMTP を切替）
 */
function send_one(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    global $CONFIG;

    if (($CONFIG['mail_method'] ?? 'mail') === 'smtp') {
        $mailer = new SmtpMailer($CONFIG['smtp']);
        $ok = $mailer->send(
            $CONFIG['from_email'], $CONFIG['from_name'],
            $to, $subject, $body, $replyTo
        );
        if (!$ok) {
            error_log('[reserve][smtp] ' . $mailer->lastError);
        }
        return $ok;
    }

    // mail() / mb_send_mail 経由
    $headers = 'From: ' . mb_encode_mimeheader($CONFIG['from_name'], 'UTF-8', 'B', "\r\n")
        . ' <' . $CONFIG['from_email'] . '>';
    if ($replyTo !== '') {
        $headers .= "\r\n" . 'Reply-To: ' . $replyTo;
    }
    return mb_send_mail($to, $subject, $body, $headers);
}

/**
 * メール送信（管理者通知 + お客様への自動返信）
 * @return bool 2通とも送信に成功したか
 */
function send_reservation_mails(array $d): bool
{
    global $CONFIG;
    $ev = $CONFIG['event'];

    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');

    // --- 1) 管理者宛 通知メール ---
    $adminBody =
        "OSAKA TENDER WEEKENDERS の予約フォームから新しい予約が届きました。\n" .
        "----------------------------------------\n" .
        "お名前　　： {$d['name']}\n" .
        "ふりがな　： {$d['kana']}\n" .
        "枚数　　　： " . qty_label($d['qty']) . "\n" .
        "メール　　： {$d['email']}\n" .
        "電話番号　： {$d['tel']}\n" .
        "----------------------------------------\n" .
        "受付日時　： {$now}\n" .
        "\n※ お客様には整理番号を追ってご連絡ください。\n";

    $okAdmin = send_one($CONFIG['admin_email'], $CONFIG['subject_admin'], $adminBody, $d['email']);

    // --- 2) お客様宛 自動返信（サンキューメール） ---
    $userBody =
        "{$d['name']} 様\n\n" .
        "この度は「{$ev['title']}」へのご予約ありがとうございます。\n" .
        "下記の内容でご予約を受け付けました。\n\n" .
        "■ ご予約内容\n" .
        "----------------------------------------\n" .
        "お名前　　： {$d['name']}\n" .
        "ふりがな　： {$d['kana']}\n" .
        "枚数　　　： " . qty_label($d['qty']) . "\n" .
        "メール　　： {$d['email']}\n" .
        "電話番号　： {$d['tel']}\n" .
        "----------------------------------------\n\n" .
        "■ 公演情報\n" .
        "日　時： {$ev['date']}（{$ev['open']}）\n" .
        "会　場： {$ev['venue']}\n" .
        "料　金： {$ev['price']}\n\n" .
        "※ 整理番号は、追って担当者よりご連絡いたします。今しばらくお待ちください。\n" .
        "※ 本メールは送信専用です。ご不明な点は下記までご連絡ください。\n\n" .
        "----------------------------------------\n" .
        "{$CONFIG['from_name']}\n" .
        "お問い合わせ： {$CONFIG['admin_email']}\n";

    $okUser = send_one($d['email'], $CONFIG['subject_user'], $userBody, $CONFIG['admin_email']);

    return $okAdmin && $okUser;
}

/** ページ共通ヘッダ出力 */
function render_head(string $title): void
{
    echo '<!DOCTYPE html><html lang="ja"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex">';
    echo '<title>' . h($title) . ' | OSAKA TENDER WEEKENDERS</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Shippori+Mincho:wght@400;500;600;700&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="../styles.css">';
    echo '</head><body class="formpage"><main class="fp-wrap">';
}

/** ページ共通フッタ出力 */
function render_foot(): void
{
    echo '<div class="fp-credit">OSAKA TENDER WEEKENDERS &nbsp;·&nbsp; 2026</div>';
    echo '</main></body></html>';
}

/** 入力ページ（index.html）へ戻す */
function redirect_to_form(): void
{
    header('Location: ../index.html#reserve');
    exit;
}
