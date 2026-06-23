<?php
/**
 * 依存ライブラリ不要の最小SMTPクライアント
 * STARTTLS / SSL / AUTH LOGIN に対応。テキスト(UTF-8, base64)メールを1通送信。
 */
declare(strict_types=1);

final class SmtpMailer
{
    private array $cfg;
    /** @var resource|false */
    private $conn = false;
    public string $lastError = '';

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    private function fail(string $msg): bool
    {
        $this->lastError = $msg;
        if (is_resource($this->conn)) {
            @fclose($this->conn);
        }
        return false;
    }

    /** サーバー応答を1レスポンス分（複数行対応）読み取る */
    private function read(): string
    {
        $data = '';
        while (is_resource($this->conn) && ($line = fgets($this->conn, 515)) !== false) {
            $data .= $line;
            // "250-..." は継続行、"250 ..." が最終行
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    /** コマンド送信＋応答コード検証 */
    private function cmd(string $cmd, $expect): bool
    {
        if ($cmd !== '') {
            if (@fwrite($this->conn, $cmd . "\r\n") === false) {
                return $this->fail('送信に失敗しました。');
            }
        }
        $resp = $this->read();
        $code = (int)substr($resp, 0, 3);
        foreach ((array)$expect as $ok) {
            if ($code === $ok) {
                return true;
            }
        }
        return $this->fail('SMTP応答エラー (期待 ' . implode('/', (array)$expect) . '): ' . trim($resp));
    }

    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $body,
        string $replyTo = ''
    ): bool {
        $c   = $this->cfg;
        $enc = $c['encryption'] ?? 'tls';
        $timeout = (int)($c['timeout'] ?? 15);
        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $c['host'] . ':' . (int)$c['port'];

        $errno = 0; $errstr = '';
        $this->conn = @stream_socket_client(
            $remote, $errno, $errstr, $timeout,
            STREAM_CLIENT_CONNECT, stream_context_create()
        );
        if (!$this->conn) {
            return $this->fail("接続失敗: {$errstr} ({$errno})");
        }
        stream_set_timeout($this->conn, $timeout);

        if (!$this->cmd('', 220)) return false;

        $ehlo = 'EHLO ' . $this->ehloName();
        if (!$this->cmd($ehlo, 250)) return false;

        // STARTTLS
        if ($enc === 'tls') {
            if (!$this->cmd('STARTTLS', 220)) return false;
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!@stream_socket_enable_crypto($this->conn, true, $crypto)) {
                return $this->fail('STARTTLS の暗号化に失敗しました。');
            }
            if (!$this->cmd($ehlo, 250)) return false; // TLS確立後に再EHLO
        }

        // AUTH LOGIN
        if (!empty($c['auth'])) {
            if (!$this->cmd('AUTH LOGIN', 334)) return false;
            if (!$this->cmd(base64_encode((string)($c['username'] ?? '')), 334)) return false;
            if (!$this->cmd(base64_encode((string)($c['password'] ?? '')), 235)) return false;
        }

        if (!$this->cmd('MAIL FROM:<' . $fromEmail . '>', 250)) return false;
        if (!$this->cmd('RCPT TO:<' . $toEmail . '>', [250, 251])) return false;
        if (!$this->cmd('DATA', 354)) return false;

        $payload = $this->buildMessage($fromEmail, $fromName, $toEmail, $subject, $body, $replyTo);
        if (!$this->cmd($payload, 250)) return false;

        $this->cmd('QUIT', [221, 250]); // 失敗しても無視
        if (is_resource($this->conn)) {
            @fclose($this->conn);
        }
        return true;
    }

    private function ehloName(): string
    {
        $h = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $h = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)$h);
        return $h !== '' ? $h : 'localhost';
    }

    /** RFC準拠のヘッダ＋本文（本文はbase64）を構築し、終端 "." まで付与 */
    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $body,
        string $replyTo
    ): string {
        $date    = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('r');
        $encName = mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n");
        $encSubj = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

        $h = [];
        $h[] = 'Date: ' . $date;
        $h[] = 'From: ' . $encName . ' <' . $fromEmail . '>';
        $h[] = 'To: <' . $toEmail . '>';
        if ($replyTo !== '') {
            $h[] = 'Reply-To: <' . $replyTo . '>';
        }
        $h[] = 'Subject: ' . $encSubj;
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'Content-Type: text/plain; charset="UTF-8"';
        $h[] = 'Content-Transfer-Encoding: base64';

        $encodedBody = chunk_split(base64_encode($body), 76, "\r\n");

        $message = implode("\r\n", $h) . "\r\n\r\n" . $encodedBody;
        // 終端は CRLF "." （cmd() が末尾に CRLF を付与する）
        return rtrim($message, "\r\n") . "\r\n.";
    }
}
