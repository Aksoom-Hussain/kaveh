<?php

declare(strict_types=1);

namespace Kaveh\Support;

final class Redactor
{
    public const MASK = '[REDACTED]';

    /**
     * Normalised substrings that mark a key as secret. Matched against the key
     * with `_`, `-` and case stripped, so `access_token`, `apiKey`, `X-Auth-Token`
     * and `stripe_secret` all match. Extend via `kaveh.redact.key_patterns`.
     *
     * @var list<string>
     */
    public const SECRET_PATTERNS = [
        'password', 'passwd', 'secret', 'token', 'apikey', 'accesskey', 'privatekey',
        // `sessionid` not `session`: the request watcher's own `session` context
        // key must stay readable, and its contents are already allowlisted.
        'auth', 'credential', 'cookie', 'sessionid', 'creditcard', 'cardnumber',
        'cvv', 'cvc', 'ssn', 'iban', 'otp',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function redact(array $data, int $depth = 0): array
    {
        if ($depth > 8) {
            return ['_truncated' => true];
        }

        $maxString = (int) config('kaveh.max_string_length', 2000);
        $result = [];

        foreach ($data as $key => $value) {
            $keyStr = (string) $key;
            if ($this->isSecretKey($keyStr)) {
                $result[$keyStr] = self::MASK;
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

        return $depth === 0
            // Fallback must match the config default (config/kaveh.php:94).
            ? $this->enforceByteBudget($result, (int) config('kaveh.max_context_bytes', 65536))
            : $result;
    }

    /**
     * Trim the fattest fields until the payload fits. The rest of the event
     * survives — one oversized blob must not erase the status code and URI.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function enforceByteBudget(array $result, int $maxBytes): array
    {
        $encoded = json_encode($result);
        if ($encoded === false || strlen($encoded) <= $maxBytes) {
            return $result;
        }

        $sizes = [];
        foreach ($result as $key => $value) {
            $sizes[$key] = strlen((string) json_encode($value));
        }
        arsort($sizes);

        foreach ($sizes as $key => $size) {
            $result[$key] = ['_truncated' => true, '_bytes' => $size];
            $encoded = json_encode($result);
            if ($encoded !== false && strlen($encoded) <= $maxBytes) {
                break;
            }
        }

        return $result;
    }

    /**
     * Case/separator-insensitive substring match against the secret pattern list
     * plus the exact keys configured in `kaveh.redact.keys`.
     */
    private function isSecretKey(string $key): bool
    {
        $normalised = str_replace(['_', '-', ' ', '.'], '', strtolower($key));

        foreach ((array) config('kaveh.redact.keys', []) as $configured) {
            if ($normalised === str_replace(['_', '-', ' ', '.'], '', strtolower((string) $configured))) {
                return true;
            }
        }

        $patterns = (array) config('kaveh.redact.key_patterns', self::SECRET_PATTERNS);
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && str_contains($normalised, strtolower((string) $pattern))) {
                return true;
            }
        }

        return false;
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
            // Same substring matcher as keys, so proxy-authorization, x-auth-token
            // and set-cookie are covered without enumerating every spelling.
            $out[$name] = in_array(strtolower($name), $blocked, true) || $this->isSecretKey($name)
                ? self::MASK
                : $value;
        }

        return $out;
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return self::MASK;
        }
        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 1).'***@'.$domain;
    }
}
