<?php

declare(strict_types=1);

namespace Kaveh\Support;

final class Redactor
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function redact(array $data, int $depth = 0): array
    {
        if ($depth > 8) {
            return ['_truncated' => true];
        }

        $keys = array_map('strtolower', (array) config('kaveh.redact.keys', []));
        $maxString = (int) config('kaveh.max_string_length', 2000);
        $maxBytes = (int) config('kaveh.max_context_bytes', 32768);
        $result = [];

        foreach ($data as $key => $value) {
            $keyStr = (string) $key;
            if (in_array(strtolower($keyStr), $keys, true)) {
                $result[$keyStr] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $result[$keyStr] = $this->redact($value, $depth + 1);
            } elseif (is_string($value)) {
                $result[$keyStr] = mb_strlen($value) > $maxString
                    ? mb_substr($value, 0, $maxString).'…'
                    : $value;
            } elseif (is_scalar($value) || $value === null) {
                $result[$keyStr] = $value;
            } else {
                $result[$keyStr] = '[object]';
            }
        }

        $encoded = json_encode($result);
        if ($encoded !== false && strlen($encoded) > $maxBytes) {
            return ['_truncated' => true, '_bytes' => strlen($encoded)];
        }

        return $result;
    }

    /**
     * @param  array{id?: string|int|null, name?: string|null, email?: string|null}|null  $user
     * @return array{id?: string|int|null, name?: string|null, email?: string|null}|null
     */
    public function redactUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user['id'] ?? null,
            'name' => isset($user['name']) ? (string) $user['name'] : null,
            'email' => isset($user['email']) ? $this->maskEmail((string) $user['email']) : null,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public function redactHeaders(array $headers): array
    {
        $blocked = array_map('strtolower', (array) config('kaveh.redact.headers', []));
        $out = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $blocked, true)) {
                $out[$name] = '[REDACTED]';
            } else {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '[REDACTED]';
        }
        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 1).'***@'.$domain;
    }
}
