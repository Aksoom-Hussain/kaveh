<?php

namespace Kaveh\Server\Console\Commands;

use Kaveh\Server\Services\Alerts\AlertEvaluator;
use Illuminate\Console\Command;

class EvaluateAlertsCommand extends Command
{
    protected $signature = 'kaveh:evaluate-alerts';

    protected $description = 'Evaluate alert rules and fire notifications';

    public function handle(AlertEvaluator $evaluator): int
    {
        $fired = $evaluator->evaluateAll();
        $this->info("Fired {$fired} alerts.");

        return self::SUCCESS;
    }
}
