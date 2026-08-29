<?php

declare(strict_types=1);

namespace Kaveh\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Kaveh\Server\Models\AlertFiring;
use Kaveh\Server\Models\AlertRule;
use Kaveh\Server\Models\EventRecord;
use Kaveh\Server\Models\Organization;
use Kaveh\Server\Models\Project;
use Kaveh\Server\Services\Alerts\AlertEvaluator;

/**
 * A rule must fire when its condition is met and stay quiet when it is not —
 * a monitoring package that cries wolf is as broken as one that stays silent.
 */
final class AlertEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private AlertEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        // Mail::fake() does not record Mailer::raw(), so use the array transport.
        config(['mail.default' => 'array']);

        $org = Organization::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        $this->project = Project::query()->create([
            'organization_id' => $org->id,
            'name' => 'Web',
            'slug' => 'web',
        ]);
        $this->evaluator = new AlertEvaluator;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(array $attributes = []): AlertRule
    {
        return AlertRule::query()->create(array_merge([
            'project_id' => $this->project->id,
            'name' => 'Too many exceptions',
            'metric' => 'exception_rate',
            'threshold' => 3,
            'window_minutes' => 5,
            'cooldown_minutes' => 30,
            'channel' => 'webhook',
            'target' => 'https://hooks.test/kaveh',
            'enabled' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function events(int $count, array $attributes = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            EventRecord::query()->create(array_merge([
                'project_id' => $this->project->id,
                'uuid' => 'evt-'.uniqid('', true),
                'type' => 'exception',
                'name' => 'RuntimeException',
                'level' => 'error',
                'occurred_at' => now()->subMinute(),
            ], $attributes));
        }
    }

    public function test_it_fires_when_the_threshold_is_reached(): void
    {
        $rule = $this->rule(['threshold' => 3]);
        $this->events(3);

        $this->assertTrue($this->evaluator->evaluate($rule));
        $this->assertNotNull($rule->fresh()->last_fired_at);

        $firing = AlertFiring::query()->where('alert_rule_id', $rule->id)->sole();
        $this->assertSame(3, $firing->count);
        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/kaveh'
            && $request['count'] === 3
            && $request['project_id'] === $this->project->id);
    }

    public function test_it_does_not_fire_below_the_threshold(): void
    {
        $rule = $this->rule(['threshold' => 3]);
        $this->events(2);

        $this->assertFalse($this->evaluator->evaluate($rule));
        $this->assertNull($rule->fresh()->last_fired_at);
        $this->assertSame(0, AlertFiring::query()->count());
        Http::assertNothingSent();
    }

    public function test_events_outside_the_window_do_not_count(): void
    {
        $rule = $this->rule(['threshold' => 3, 'window_minutes' => 5]);
        $this->events(3, ['occurred_at' => now()->subHours(2)]);

        $this->assertFalse($this->evaluator->evaluate($rule));
    }

    public function test_events_from_another_project_do_not_count(): void
    {
        $otherOrg = Organization::query()->create(['name' => 'Other', 'slug' => 'other']);
        $other = Project::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other',
            'slug' => 'other',
        ]);

        $rule = $this->rule(['threshold' => 2]);
        $this->events(5, ['project_id' => $other->id]);

        $this->assertFalse($this->evaluator->evaluate($rule));
    }

    public function test_the_cooldown_suppresses_a_repeat_firing(): void
    {
        $rule = $this->rule(['threshold' => 1, 'cooldown_minutes' => 30]);
        $this->events(5);

        $this->assertTrue($this->evaluator->evaluate($rule));
        $this->assertFalse($this->evaluator->evaluate($rule->fresh()), 'the rule re-fired inside its cooldown');
        $this->assertSame(1, AlertFiring::query()->count());
    }

    public function test_it_fires_again_once_the_cooldown_has_elapsed(): void
    {
        $rule = $this->rule(['threshold' => 1, 'cooldown_minutes' => 30]);
        $this->events(5);
        $rule->forceFill(['last_fired_at' => now()->subMinutes(31)])->save();

        $this->assertTrue($this->evaluator->evaluate($rule));
    }

    public function test_failed_jobs_metric_only_counts_failed_jobs(): void
    {
        $rule = $this->rule(['metric' => 'failed_jobs', 'threshold' => 2]);
        $this->events(2, ['type' => 'job', 'level' => 'info', 'name' => 'SendMail']);
        $this->events(1, ['type' => 'job', 'level' => 'error', 'name' => 'SendMail']);
        $this->events(5, ['type' => 'exception']);

        $this->assertFalse($this->evaluator->evaluate($rule), 'succeeded jobs counted toward failed_jobs');

        $this->events(1, ['type' => 'job', 'level' => 'error', 'name' => 'SendMail']);
        $this->assertTrue($this->evaluator->evaluate($rule));
    }

    public function test_custom_event_metric_matches_on_event_name(): void
    {
        $rule = $this->rule([
            'metric' => 'custom_event',
            'event_name' => 'checkout.failed',
            'threshold' => 2,
        ]);
        $this->events(5, ['type' => 'custom', 'name' => 'checkout.succeeded']);
        $this->events(1, ['type' => 'custom', 'name' => 'checkout.failed']);

        $this->assertFalse($this->evaluator->evaluate($rule));

        $this->events(1, ['type' => 'custom', 'name' => 'checkout.failed']);
        $this->assertTrue($this->evaluator->evaluate($rule));
    }

    public function test_an_unknown_metric_never_fires(): void
    {
        $rule = $this->rule(['metric' => 'made_up_metric', 'threshold' => 1]);
        $this->events(50);

        $this->assertFalse($this->evaluator->evaluate($rule));
    }

    public function test_email_channel_sends_mail_instead_of_a_webhook(): void
    {
        $rule = $this->rule([
            'threshold' => 1,
            'channel' => 'email',
            'target' => 'ops@example.com',
        ]);
        $this->events(1);

        $this->assertTrue($this->evaluator->evaluate($rule));
        Http::assertNothingSent();

        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages[0]->getOriginalMessage();
        $this->assertSame('ops@example.com', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('[Kaveh] Too many exceptions', $message->getSubject());
        $this->assertStringContainsString('1 events in 5 minutes', $message->getTextBody());
    }

    public function test_a_failing_webhook_does_not_abort_the_firing(): void
    {
        Http::fake(fn () => throw new \RuntimeException('hook unreachable'));
        $rule = $this->rule(['threshold' => 1]);
        $this->events(1);

        $this->assertTrue($this->evaluator->evaluate($rule));
        $this->assertSame(1, AlertFiring::query()->count());
    }

    public function test_evaluate_all_skips_disabled_rules(): void
    {
        $this->rule(['threshold' => 1, 'enabled' => false]);
        $this->rule(['name' => 'Enabled', 'threshold' => 1]);
        $this->events(2);

        $this->assertSame(1, $this->evaluator->evaluateAll());
    }
}
