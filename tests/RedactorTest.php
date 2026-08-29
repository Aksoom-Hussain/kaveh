<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Kaveh\Support\Redactor;

/**
 * Redaction is the leak path: whatever survives redact() leaves the host app.
 */
final class RedactorTest extends TestCase
{
    private Redactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new Redactor;
    }

    public function test_configured_secret_keys_are_scrubbed(): void
    {
        $out = $this->redactor->redact([
            'password' => 'hunter2',
            'password_confirmation' => 'hunter2',
            'token' => 'tok_live_abc',
            'api_key' => 'kv_abc',
            'secret' => 's3cr3t',
            'authorization' => 'Bearer abc',
            'cookie' => 'session=abc',
            'credit_card' => '4111111111111111',
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'ssn' => '111-22-3333',
        ]);

        foreach ($out as $key => $value) {
            $this->assertSame('[REDACTED]', $value, "$key was not redacted");
        }
    }

    public function test_non_secret_keys_survive(): void
    {
        $out = $this->redactor->redact(['order_id' => 7, 'email_subject' => 'hi', 'ok' => true]);

        $this->assertSame(['order_id' => 7, 'email_subject' => 'hi', 'ok' => true], $out);
    }

    public function test_key_matching_is_case_insensitive(): void
    {
        $out = $this->redactor->redact(['Password' => 'x', 'API_KEY' => 'y', 'Authorization' => 'z']);

        $this->assertSame(['Password' => '[REDACTED]', 'API_KEY' => '[REDACTED]', 'Authorization' => '[REDACTED]'], $out);
    }

    public function test_nested_arrays_are_redacted_at_every_level(): void
    {
        $out = $this->redactor->redact([
            'user' => [
                'name' => 'ada',
                'profile' => [
                    'password' => 'hunter2',
                    'meta' => ['token' => 'tok_live', 'keep' => 'yes'],
                ],
            ],
            'payload' => [
                ['secret' => 'a', 'id' => 1],
                ['secret' => 'b', 'id' => 2],
            ],
        ]);

        $this->assertSame('ada', $out['user']['name']);
        $this->assertSame('[REDACTED]', $out['user']['profile']['password']);
        $this->assertSame('[REDACTED]', $out['user']['profile']['meta']['token']);
        $this->assertSame('yes', $out['user']['profile']['meta']['keep']);
        $this->assertSame('[REDACTED]', $out['payload'][0]['secret']);
        $this->assertSame('[REDACTED]', $out['payload'][1]['secret']);
        $this->assertSame(2, $out['payload'][1]['id']);

        $this->assertStringNotContainsString('hunter2', (string) json_encode($out));
        $this->assertStringNotContainsString('tok_live', (string) json_encode($out));
    }

    public function test_custom_configured_keys_are_honoured(): void
    {
        config(['kaveh.redact.keys' => ['internal_pin']]);

        $out = $this->redactor->redact(['internal_pin' => '0000', 'password' => 'still-caught']);

        $this->assertSame('[REDACTED]', $out['internal_pin']);
        // Configured keys are additive: the built-in patterns are a floor, not a
        // default that a shorter config list can silently switch off.
        $this->assertSame('[REDACTED]', $out['password']);
    }

    /**
     * The bug: exact-key matching shipped every one of these verbatim.
     */
    public function test_secret_key_names_are_matched_by_substring(): void
    {
        $keys = [
            'access_token', 'refresh_token', 'id_token', 'client_secret', 'private_key',
            'stripe_secret', 'api-key', 'apiKey', 'APIKey', 'db_password', 'dbPassword',
            'aws_secret_access_key', 'webhook_secret', 'x-auth-token', 'bearerToken',
            'user_credentials', 'sessionId', 'otp', 'iban', 'cvc',
        ];

        $out = $this->redactor->redact(array_fill_keys($keys, 'leaky-value'));

        foreach ($keys as $key) {
            $this->assertSame('[REDACTED]', $out[$key], "$key escaped redaction");
        }
        $this->assertStringNotContainsString('leaky-value', (string) json_encode($out));
    }

    public function test_substring_matching_reaches_into_nested_arrays(): void
    {
        $out = $this->redactor->redact([
            'provider' => [
                'name' => 'github',
                'grant' => ['access_token' => 'gho_leaky', 'refreshToken' => 'ghr_leaky'],
            ],
            'billing' => [['stripe_secret' => 'sk_live_leaky', 'amount' => 100]],
        ]);

        $this->assertSame('github', $out['provider']['name']);
        $this->assertSame('[REDACTED]', $out['provider']['grant']['access_token']);
        $this->assertSame('[REDACTED]', $out['provider']['grant']['refreshToken']);
        $this->assertSame('[REDACTED]', $out['billing'][0]['stripe_secret']);
        $this->assertSame(100, $out['billing'][0]['amount']);
        $this->assertStringNotContainsString('leaky', (string) json_encode($out));
    }

    public function test_a_secret_named_key_holding_an_array_is_redacted_wholesale(): void
    {
        $out = $this->redactor->redact([
            'credentials' => ['username' => 'ada', 'password' => 'hunter2'],
            'order_id' => 7,
        ]);

        $this->assertSame('[REDACTED]', $out['credentials']);
        $this->assertSame(7, $out['order_id']);
    }

    public function test_ordinary_keys_are_not_swept_up_by_the_patterns(): void
    {
        $out = $this->redactor->redact([
            'order_id' => 7,
            'monkey' => 'not a key',
            'keyboard' => 'mechanical',
            'tokenizer_version' => 3,
        ]);

        $this->assertSame(7, $out['order_id']);
        $this->assertSame('not a key', $out['monkey']);
        $this->assertSame('mechanical', $out['keyboard']);
        // `tokenizer_version` does contain "token" — a deliberate false positive:
        // over-redacting a version number beats shipping an auth token.
        $this->assertSame('[REDACTED]', $out['tokenizer_version']);
    }

    public function test_objects_are_never_serialised_into_the_payload(): void
    {
        $out = $this->redactor->redact(['model' => new \stdClass, 'closureish' => fopen('php://memory', 'r')]);

        $this->assertSame('[object]', $out['model']);
        $this->assertSame('[object]', $out['closureish']);
    }

    public function test_long_strings_are_truncated(): void
    {
        config(['kaveh.max_string_length' => 10]);

        $out = $this->redactor->redact(['blob' => str_repeat('a', 50)]);

        $this->assertSame(str_repeat('a', 10).'…', $out['blob']);
    }

    public function test_oversized_field_is_trimmed_and_the_rest_of_the_event_survives(): void
    {
        config(['kaveh.max_context_bytes' => 200, 'kaveh.max_string_length' => 100000]);

        $out = $this->redactor->redact([
            'uri' => '/orders/17',
            'status' => 500,
            'blob' => str_repeat('a', 5000),
        ]);

        // The offending field goes, everything you need to read the event stays.
        $this->assertSame('/orders/17', $out['uri']);
        $this->assertSame(500, $out['status']);
        $this->assertTrue($out['blob']['_truncated']);
        $this->assertGreaterThan(5000, $out['blob']['_bytes']);
        $this->assertLessThanOrEqual(200, strlen((string) json_encode($out)));
    }

    public function test_only_the_fields_needed_to_fit_are_trimmed(): void
    {
        config(['kaveh.max_context_bytes' => 300, 'kaveh.max_string_length' => 100000]);

        $out = $this->redactor->redact([
            'small' => str_repeat('s', 20),
            'huge' => str_repeat('h', 5000),
        ]);

        $this->assertSame(str_repeat('s', 20), $out['small']);
        $this->assertTrue($out['huge']['_truncated']);
    }

    public function test_byte_budget_fallback_matches_the_config_default(): void
    {
        config(['kaveh' => \Illuminate\Support\Arr::except((array) config('kaveh'), ['max_context_bytes'])]);
        config(['kaveh.max_string_length' => 100000]);

        // 40KB is over the old 32768 fallback and under the real 65536 default.
        $out = $this->redactor->redact(['blob' => str_repeat('a', 40000)]);

        $this->assertSame(str_repeat('a', 40000), $out['blob']);
    }

    public function test_recursion_is_bounded(): void
    {
        $deep = ['password' => 'leak'];
        for ($i = 0; $i < 12; $i++) {
            $deep = ['nested' => $deep];
        }

        $out = $this->redactor->redact($deep);

        $this->assertStringNotContainsString('leak', (string) json_encode($out));
        $this->assertStringContainsString('_truncated', (string) json_encode($out));
    }

    public function test_headers_are_redacted_case_insensitively(): void
    {
        $out = $this->redactor->redactHeaders([
            'Authorization' => 'Bearer kv_live_abc',
            'Cookie' => 'session=abc',
            'X-Api-Key' => 'kv_abc',
            'X-CSRF-TOKEN' => 'csrf',
            'Accept' => 'application/json',
        ]);

        $this->assertSame('[REDACTED]', $out['Authorization']);
        $this->assertSame('[REDACTED]', $out['Cookie']);
        $this->assertSame('[REDACTED]', $out['X-Api-Key']);
        $this->assertSame('[REDACTED]', $out['X-CSRF-TOKEN']);
        $this->assertSame('application/json', $out['Accept']);
    }

    public function test_headers_outside_the_configured_list_are_still_caught(): void
    {
        $out = $this->redactor->redactHeaders([
            'Proxy-Authorization' => 'Basic abc',
            'X-Auth-Token' => 'leaky',
            'X-Forwarded-Authorization' => 'Bearer leaky',
            'Set-Cookie' => 'session=abc',
            'Content-Type' => 'application/json',
            'User-Agent' => 'curl/8',
        ]);

        $this->assertSame('[REDACTED]', $out['Proxy-Authorization']);
        $this->assertSame('[REDACTED]', $out['X-Auth-Token']);
        $this->assertSame('[REDACTED]', $out['X-Forwarded-Authorization']);
        $this->assertSame('[REDACTED]', $out['Set-Cookie']);
        $this->assertSame('application/json', $out['Content-Type']);
        $this->assertSame('curl/8', $out['User-Agent']);
    }

    public function test_user_email_is_masked_and_extra_attributes_dropped(): void
    {
        $out = $this->redactor->redactUser([
            'id' => 42,
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'remember_token' => 'leaky',
        ]);

        $this->assertSame(['id' => 42, 'name' => 'Ada', 'email' => 'a***@example.com'], $out);
        $this->assertArrayNotHasKey('remember_token', $out);
    }

    public function test_malformed_email_is_fully_redacted(): void
    {
        $this->assertSame('[REDACTED]', $this->redactor->redactUser(['email' => 'not-an-email'])['email']);
        $this->assertNull($this->redactor->redactUser(null));
    }
}
