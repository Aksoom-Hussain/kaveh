<?php

namespace Kaveh\Server\Services\Alerts;

use Kaveh\Server\Models\AlertFiring;
use Kaveh\Server\Models\AlertRule;
use Kaveh\Server\Models\EventRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertEvaluator
{
    public function evaluateAll(): int
    {
        $fired = 0;
        AlertRule::query()->where('enabled', true)->with('project')->each(function (AlertRule $rule) use (&$fired) {
            if ($this->evaluate($rule)) {
                $fired++;
            }
        });

        return $fired;
    }

    public function evaluate(AlertRule $rule): bool
    {
        if ($rule->last_fired_at && $rule->last_fired_at->gt(now()->subMinutes($rule->cooldown_minutes))) {
            return false;
        }

        $since = now()->subMinutes($rule->window_minutes);
        $query = EventRecord::query()
            ->where('project_id', $rule->project_id)
            ->where('occurred_at', '>=', $since);

        match ($rule->metric) {
            'exception_rate' => $query->where('type', 'exception'),
            'failed_jobs' => $query->where('type', 'job')->where('level', 'error'),
            'custom_event' => $query->where('type', 'custom')->when(
                $rule->event_name,
                fn ($q) => $q->where('name', $rule->event_name)
            ),
            default => $query->whereRaw('1=0'),
        };

        $count = $query->count();
        if ($count < $rule->threshold) {
            return false;
        }

        $this->notify($rule, $count);
        $rule->forceFill(['last_fired_at' => now()])->save();
        AlertFiring::query()->create([
            'alert_rule_id' => $rule->id,
            'count' => $count,
            'meta' => [
                'window_minutes' => $rule->window_minutes,
                'threshold' => $rule->threshold,
            ],
        ]);

        return true;
    }

    private function notify(AlertRule $rule, int $count): void
    {
        $message = sprintf(
            'Kaveh alert "%s" fired for project #%d: %d events in %d minutes (threshold %d).',
            $rule->name,
            $rule->project_id,
            $count,
            $rule->window_minutes,
            $rule->threshold
        );

        if ($rule->channel === 'webhook') {
            try {
                Http::timeout(10)->post($rule->target, [
                    'alert' => $rule->name,
                    'project_id' => $rule->project_id,
                    'count' => $count,
                    'message' => $message,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Kaveh webhook alert failed', ['message' => $e->getMessage()]);
            }

            return;
        }

        if ($rule->channel === 'email') {
            try {
                Mail::raw($message, function ($mail) use ($rule) {
                    $mail->to($rule->target)->subject('[Kaveh] '.$rule->name);
                });
            } catch (\Throwable $e) {
                Log::warning('Kaveh email alert failed', ['message' => $e->getMessage()]);
            }
        }
    }
}
