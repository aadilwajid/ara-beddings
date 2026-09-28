<?php
/** SMTP mailer over raw sockets (no Composer dependency → Vercel friendly).
 *  All SMTP settings come from environment variables (MAIL_*). */

declare(strict_types=1);

final class Mailer
{
    private ?string $error = null;

    public static function make(): self { return new self(); }

    /** Send an email. Returns true on success. Never throws in production paths —
     *  callers log the failure but checkout continues. */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        global $CONFIG;
        $host = $CONFIG['mail']['host'];
        if (!$host) {
            $this->error = 'MAIL_HOST not configured — email skipped.';
            error_log($this->error . " Recipient: {$to}, Subject: {$subject}");
            return false;
        }
        try {
            $port   = (int)$CONFIG['mail']['port'];
            $secure = str_starts_with($host, 'ssl://') ? 'ssl://' : '';
            $fp = @stream_socket_client($secure . $host . ":$port", $errno, $errstr, 15);
            if (!$fp) throw new RuntimeException("connect failed: $errstr");

            $this->expect($fp, '220');
            $this->cmd($fp, 'EHLO ' . parse_url(APP_URL, PHP_URL_HOST), '250');

            // STARTTLS on port 587 when supported
            if ($port === 587 && !$secure) {
                $this->cmd($fp, 'STARTTLS', '220');
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS negotiation failed');
                }
                $this->cmd($fp, 'EHLO ' . parse_url(APP_URL, PHP_URL_HOST), '250');
            }

            if (!empty($CONFIG['mail']['username'])) {
                $this->cmd($fp, 'AUTH LOGIN', '334');
                $this->cmd($fp, base64_encode($CONFIG['mail']['username']), '334');
                $this->cmd($fp, base64_encode($CONFIG['mail']['password']), '235');
            }

            $from = $CONFIG['mail']['from'];
            $this->cmd($fp, "MAIL FROM:<{$from}>", '250');
            $this->cmd($fp, "RCPT TO:<{$to}>", ['250', '251']);

            $boundary = 'bnd' . bin2hex(random_bytes(8));
            $headers = implode("\r\n", [
                'From: ' . $from,
                'To: ' . $to,
                'Subject: ' . $subject,
                'Date: ' . date('r'),
                'MIME-Version: 1.0',
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            ]);
            $plain = strip_tags(str_replace('<br', "\n<br", $htmlBody));
            $msg = "{$headers}\r\n\r\n"
                 . "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $plain . "\r\n"
                 . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n"
                 . "--{$boundary}--\r\n";

            $this->cmd($fp, 'DATA', '354');
            fwrite($fp, str_replace("\n.", "\n..", $msg) . "\r\n.\r\n");
            $this->expect($fp, '250');
            fwrite($fp, "QUIT\r\n");
            fclose($fp);
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    public function lastError(): ?string { return $this->error; }

    private function cmd($fp, string $line, string|array $want): void
    {
        fwrite($fp, $line . "\r\n");
        $this->expect($fp, $want);
    }

    private function expect($fp, string|array $want): void
    {
        $resp = '';
        while ($line = fgets($fp, 512)) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = substr(trim($resp), 0, 3);
        $want = (array)$want;
        if (!in_array($code, $want, true)) {
            throw new RuntimeException('SMTP expected ' . implode('/', $want) . ", got: $resp");
        }
    }
}

/** Convenience wrappers used by controllers. */
function mail_template(string $title, string $bodyHtml): string
{
    $store = e(setting('store_name', 'Store'));
    return "<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto'>"
         . "<h2 style='color:#7c3aed'>{$store}</h2>"
         . "<h3>" . e($title) . "</h3>" . $bodyHtml
         . "<hr><small>This is an automated message from {$store}.</small></div>";
}
