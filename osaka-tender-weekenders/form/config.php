<?php
/**
 * 予約フォーム設定 / Reservation form config
 * ----------------------------------------------------------
 * 運用に合わせてこのファイルだけ書き換えれば対応できます。
 */

return [
    // 予約通知の送信先（主催・担当者）
    'admin_email' => 'info@bookends.me',
    'admin_name'  => 'OSAKA TENDER WEEKENDERS 予約担当',

    // 送信元（From）。
    //  ★エックスサーバーで作成した「送信用メールボックス」のアドレスに変更してください。
    //    （独自ドメインのアドレスにすると SPF が通り、迷惑メール判定されにくくなります）
    'from_email'  => 'info@bookends.me',   // ★要変更：作成済みの送信用アドレス
    'from_name'   => 'OSAKA TENDER WEEKENDERS',

    // メール件名
    'subject_admin' => '【予約受付】OSAKA TENDER WEEKENDERS 2026.08.09',
    'subject_user'  => '【OSAKA TENDER WEEKENDERS】ご予約を受け付けました',

    // ----------------------------------------------------------
    // メール送信方式 / Mail transport
    //   'mail' … PHP の mail()（mb_send_mail）。サーバーのMTA経由。
    //   'smtp' … 下記 SMTP サーバー経由で送信。
    // ----------------------------------------------------------
    'mail_method' => 'smtp',

    // ▼ エックスサーバー（Xserver）の設定
    //   ・host … サーバーパネル「サーバー情報」記載の「ホスト名」 svXXXX.xserver.jp
    //            （メールソフト設定画面の「送信(SMTP)サーバー」と同じ）
    //   ・port/encryption … 465 + 'ssl'（推奨）。587 + 'tls'(STARTTLS) も可
    //   ・username … 送信用メールアドレス全体（例 no-reply@ドメイン）
    //   ・password … そのメールアドレスのパスワード
    'smtp' => [
        'host'       => 'sv13300.xserver.jp',  // ★要変更：割当サーバー名
        'port'       => 465,
        'encryption' => 'ssl',
        'auth'       => true,
        'username'   => 'info@bookends.me', // ★要変更：from_email と同じ送信用アドレス
        'password'   => 'Info1029',                          // ★要変更：メールアドレスのパスワード
        'timeout'    => 15,
    ],

    // イベント情報（自動返信メール本文に記載）
    'event' => [
        'title'  => 'OSAKA TENDER WEEKENDERS 〜世界と週末に優しさを〜',
        'date'   => '2026年8月9日(日)',
        'open'   => '開場 18:00 / 開演 18:30',
        'venue'  => '大阪 北浜 雲州堂（大阪市北区菅原町7-2）',
        'price'  => '前売 ¥4,000 / 当日 ¥4,500（各 +1Drink ¥600）',
    ],
];
