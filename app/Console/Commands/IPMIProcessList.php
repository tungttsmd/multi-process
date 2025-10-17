<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IPMIProcessList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cmd = 'powershell -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq \'php.exe\' -and $_.CommandLine -match \'queue:work\' } | Select-Object ProcessId, CommandLine"';

        $p = Process::fromShellCommandline($cmd);
        $p->run();

        $output = trim($p->getOutput());

        if (empty($output)) {
            $this->warn("Không có tiến trình queue:work nào đang chạy.");
        } else {
            $this->info("Danh sách worker đang chạy:");
            $this->line($output);
        }
    }
}
