<?php

namespace App\Services\Operations;

class SecretRedactor
{
    public function redact(string $text): string
    {
        $patterns = [
            '/(bearer\s+)[A-Za-z0-9._\-]+/i' => '$1[redacted]',
            '/(authorization:\s*)[^\r\n]+/i' => '$1[redacted]',
            '/(password=)[^&\s]+/i' => '$1[redacted]',
            '/(password\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(token=)[^&\s]+/i' => '$1[redacted]',
            '/(cloudflare[_-]?token\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(github[_-]?token\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(api[_-]?key=)[^&\s]+/i' => '$1[redacted]',
            '/(api[_-]?key\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(cookie:\s*)[^\r\n]+/i' => '$1[redacted]',
            '/(67\|)[A-Za-z0-9._\-]+/' => '$1[redacted]',
            '/-----BEGIN ([A-Z ]+)?PRIVATE KEY-----.*?-----END \1PRIVATE KEY-----/is' => '[redacted-private-key]',
            '/(mysql|postgres|postgresql|redis|mongodb):\/\/[^:\s]+:[^@\s]+@/i' => '$1://[redacted]@',
            '/https?:\/\/[^:\s\/]+:[^@\s]+@/i' => 'https://[redacted]@',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $text) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/password|token|secret|cookie|key/i', (string) $key) === 1) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
