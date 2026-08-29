<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Support\Facades\Log;
use Kaveh\Support\Silencer;
use RuntimeException;

/**
 * The fail-safe must swallow client errors — but only when configured to,
 * and it must not swallow the return value of a successful call.
 */
final class SilencerTest extends TestCase
{
    public function test_successful_callback_returns_its_value_not_the_default(): void
    {
        $this->assertSame('ok', Silencer::run(fn () => 'ok', 'default'));
        $this->assertSame(0, Silencer::run(fn () => 0, 99));
        $this->assertNull(Silencer::run(fn () => null, 'default'));
    }

    public function test_failure_returns_default_instead_of_throwing(): void
    {
        config(['kaveh.fail_silently' => true]);

        $result = Silencer::run(function () {
            throw new RuntimeException('boom');
        }, 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function test_errors_not_just_exceptions_are_swallowed(): void
    {
        config(['kaveh.fail_silently' => true]);

        $result = Silencer::run(function () {
            $zero = 0;

            return intdiv(1, $zero); // DivisionByZeroError, an Error not an Exception
        }, false);

        $this->assertFalse($result);
    }

    public function test_it_does_not_hide_everything_unconditionally(): void
    {
        config(['kaveh.fail_silently' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        Silencer::run(function () {
            throw new RuntimeException('boom');
        });
    }

    public function test_swallowed_errors_are_logged_only_in_debug_mode(): void
    {
        config(['kaveh.debug' => false]);
        Log::spy();

        Silencer::run(fn () => throw new RuntimeException('quiet'));

        Log::shouldNotHaveReceived('debug');
    }

    public function test_debug_mode_surfaces_the_swallowed_error(): void
    {
        config(['kaveh.debug' => true, 'kaveh.fail_silently' => true]);
        Log::spy();

        Silencer::run(fn () => throw new RuntimeException('loud'));

        Log::shouldHaveReceived('debug')->once()->withArgs(
            fn (string $message, array $context) => $message === 'Kaveh swallowed exception'
                && $context['message'] === 'loud'
                && $context['exception'] === RuntimeException::class
        );
    }

    public function test_report_never_throws_even_when_logging_is_broken(): void
    {
        config(['kaveh.debug' => true]);
        Log::shouldReceive('debug')->andThrow(new RuntimeException('logger down'));

        Silencer::report(new RuntimeException('original'));

        $this->assertTrue(true, 'report() returned without throwing');
    }
}
