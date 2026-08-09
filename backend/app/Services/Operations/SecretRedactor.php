<?php

namespace App\Services\Operations;

class SecretRedactor
{
    public function redact(string $text): string
    {
        $patterns = [
            '/(bearer\s+)[A-Za-z0-9._\-]+/i' => '$1[redacted]',
            '/(basic\s+)[A-Za-z0-9+\/=._\-]+/i' => '$1[redacted]',
            '/(authorization:\s*)[^\r\n]+/i' => '$1[redacted]',
            '/(password=)[^&\s]+/i' => '$1[redacted]',
            '/(passwd=)[^&\s]+/i' => '$1[redacted]',
            '/(password\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(passwd\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(token=)[^&\s]+/i' => '$1[redacted]',
            '/((access|refresh|client|gateway)[_-]?token=)[^&\s]+/i' => '$1[redacted]',
            '/((access|refresh|client|gateway)[_-]?secret=)[^&\s]+/i' => '$1[redacted]',
            '/(cloudflare[_-]?token\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(github[_-]?token\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(api[_-]?key=)[^&\s]+/i' => '$1[redacted]',
            '/(apikey=)[^&\s]+/i' => '$1[redacted]',
            '/(api[_-]?key\s*[:=]\s*)[^\s,"\']+/i' => '$1[redacted]',
            '/(set-cookie:\s*)[^\r\n]+/i' => '$1[redacted]',
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
    public function scrub(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->scrubArray($value);
        }

        if (is_object($value)) {
            return $this->scrubArray((array) $value);
        }

        if (is_string($value)) {
            return $this->redact($value);
        }

        return $value;
    }

    public function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/password|passwd|token|secret|api[_-]?key|apikey|authorization|cookie|set-cookie|private[_-]?key|client[_-]?secret|access[_-]?token|refresh[_-]?token|gateway[_-]?secret/i', (string) $key) === 1) {
                $data[$key] = '[redacted]';
            } else {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }
}
