<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WorkerHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:heartbeat';
    

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates a cache timestamp to indicate the scheduler is running.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         Cache::put('scheduler_last_heartbeat', now(), now()->addMinutes(5));

         $this->info('Scheduler heartbeat has been recorded.');
    }
}
