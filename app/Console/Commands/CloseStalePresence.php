<?php

namespace App\Console\Commands;

use App\Support\Presence;
use Illuminate\Console\Command;

class CloseStalePresence extends Command
{
    protected $signature = 'app:close-stale-presence';

    protected $description = 'Clock out staff who left without logging out, and stop their task timers';

    public function handle(): int
    {
        $this->info(Presence::closeStale().' presence(s) closed.');

        return self::SUCCESS;
    }
}
