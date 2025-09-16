<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class CheckSupervisorStatus extends Command
{
    protected $signature = 'supervisor:status-check';
    protected $description = 'Checks supervisor process status and caches it.';

    public function handle()
    {
        $process = Process::fromShellCommandline('sudo supervisorctl status');
        $process->run();

        if (!$process->isSuccessful()) {
            Cache::put('supervisor_status', ['error' => 'Gagal menjalankan supervisorctl.'], now()->addMinutes(2));
            $this->error('Failed to get supervisor status.');
            return;
        }

        $output = $process->getOutput();
        $statusData = $this->parseSupervisorOutput($output);

        Cache::put('supervisor_status', $statusData, now()->addMinutes(2));
        $this->info('Supervisor status has been cached.');
    }

    private function parseSupervisorOutput(string $output): array
    {
        $processes = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (preg_match('/^([\w\-:]+)\s+([A-Z]+)\s+.*/', $line, $matches)) {
                $processes[] = [
                    'name' => trim($matches[1]),
                    'status' => trim($matches[2]),
                ];
            }
        }
        return $processes;
    }
}