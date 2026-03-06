<?php
declare(strict_types=1);

final class ContactSmtpMailer
{
    private array $config;

    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $message): void
    {
        $this->connect();

        try {
            $this->expectCodes([220]);
            $this->command('EHLO moodoo.website', [250]);

            if ($this->usesTls()) {
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS handshake failed.');
                }
                $this->command('EHLO moodoo.website', [250]);
            }

            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode((string) $this->config['username']), [334]);
            $this->command(base64_encode((string) $this->config['password']), [235]);
            $this->command('MAIL FROM:<' . $message['from_email'] . '>', [250]);
            $this->command('RCPT TO:<' . $message['to_email'] . '>', [250, 251]);
            $this->command('DATA', [354]);

            $data = $this->buildMessageData($message);
            $this->writeRaw($data . "\r\n.\r\n");
            $this->expectCodes([250]);

            $this->command('QUIT', [221]);
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        $host = (string) ($this->config['host'] ?? '');
        $port = (int) ($this->config['port'] ?? 0);
        $timeout = (int) ($this->config['timeout'] ?? 20);

        if ($host === '' || $port <= 0) {
            throw new RuntimeException('SMTP host or port is not configured.');
        }

        $remoteHost = $this->usesSsl() ? sprintf('ssl://%s:%d', $host, $port) : sprintf('%s:%d', $host, $port);
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            $remoteHost,
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (!is_resource($socket)) {
            throw new RuntimeException(
                sprintf(
                    'Unable to connect to SMTP server (%s). %s (%d)',
                    $remoteHost,
                    $errorMessage ?: 'No error message',
                    (int) $errorCode,
                ),
            );
        }

        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function usesSsl(): bool
    {
        return strtolower((string) ($this->config['encryption'] ?? 'ssl')) === 'ssl';
    }

    private function usesTls(): bool
    {
        return strtolower((string) ($this->config['encryption'] ?? 'ssl')) === 'tls';
    }

    private function command(string $command, array $expectedCodes): void
    {
        $this->writeRaw($command . "\r\n");
        $this->expectCodes($expectedCodes);
    }

    private function writeRaw(string $contents): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP connection is not available.');
        }

        $remaining = $contents;
        while ($remaining !== '') {
            $written = fwrite($this->socket, $remaining);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Failed to write to SMTP server.');
            }

            $remaining = substr($remaining, $written);
        }
    }

    private function expectCodes(array $expectedCodes): void
    {
        $response = $this->readResponse();
        $statusCode = (int) substr($response, 0, 3);

        if (!in_array($statusCode, $expectedCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }

    private function readResponse(): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP connection is not available.');
        }

        $response = '';
        while (true) {
            $line = fgets($this->socket, 515);
            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out'])) {
                    throw new RuntimeException('SMTP response timed out.');
                }
                throw new RuntimeException('Failed to read SMTP response.');
            }

            $response .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }

    private function buildMessageData(array $message): string
    {
        $subject = $this->sanitizeHeader((string) ($message['subject'] ?? 'New Contact Enquiry'));
        $fromName = $this->sanitizeHeader((string) ($message['from_name'] ?? 'Moodoo Website'));
        $replyToName = $this->sanitizeHeader((string) ($message['reply_to_name'] ?? $fromName));
        $fromEmail = $this->sanitizeEmail((string) ($message['from_email'] ?? ''));
        $toEmail = $this->sanitizeEmail((string) ($message['to_email'] ?? ''));
        $replyToEmail = $this->sanitizeEmail((string) ($message['reply_to_email'] ?? $fromEmail));
        $body = $this->normalizeBody((string) ($message['body'] ?? ''));

        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            sprintf('From: %s <%s>', $fromName, $fromEmail),
            sprintf('To: <%s>', $toEmail),
            sprintf('Reply-To: %s <%s>', $replyToName, $replyToEmail),
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function sanitizeHeader(string $value): string
    {
        $value = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
        return $value !== '' ? $value : 'Moodoo';
    }

    private function sanitizeEmail(string $value): string
    {
        $value = trim($value);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email value passed to SMTP mailer.');
        }
        return $value;
    }

    private function normalizeBody(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = explode("\n", $value);
        $normalizedLines = [];

        foreach ($lines as $line) {
            $normalizedLine = $line;
            if (str_starts_with($normalizedLine, '.')) {
                $normalizedLine = '.' . $normalizedLine;
            }
            $normalizedLines[] = $normalizedLine;
        }

        return implode("\r\n", $normalizedLines);
    }
}
