<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Alerts;

/**
 * Minimal plain-text mailer over PHP mail(). The transport is injectable so
 * tests never send real email.
 */
final class Mailer
{
    /** @var callable(string,string,string,string):bool */
    private $transport;

    /**
     * @param callable(string,string,string,string):bool|null $transport
     */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? static fn (string $to, string $subject, string $body, string $headers): bool
            => mail($to, $subject, $body, $headers);
    }

    /**
     * @param array<int, string> $recipients
     */
    public function send(array $recipients, string $subject, string $body, string $from, string $fromName): bool
    {
        $recipients = array_values(array_filter($recipients, static fn (string $r): bool => filter_var($r, FILTER_VALIDATE_EMAIL) !== false));
        if ($recipients === []) {
            return false;
        }

        $headers = 'From: ' . $this->encodeName($fromName) . ' <' . $from . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n";

        $ok = true;
        foreach ($recipients as $to) {
            $ok = ($this->transport)($to, $subject, $body, $headers) && $ok;
        }
        return $ok;
    }

    private function encodeName(string $name): string
    {
        // RFC 2047 encode if non-ASCII, else quote plainly.
        if (preg_match('/[^\x20-\x7e]/', $name)) {
            return '=?UTF-8?B?' . base64_encode($name) . '?=';
        }
        return '"' . str_replace('"', '', $name) . '"';
    }
}
