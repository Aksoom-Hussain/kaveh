<?php

declare(strict_types=1);

namespace Kaveh\Console;

use Illuminate\Console\Command;
use Kaveh\Transport\EventBuffer;

final class FlushCommand extends Command
{
    protected $signature = 'kaveh:flush';

    protected $description = 'Flush buffered Kaveh events to the remote server';

    public function handle(EventBuffer $buffer): int
    {
        $buffer->flush();
        $this->info('Kaveh buffer flushed.');

        return self::SUCCESS;
    }
}
