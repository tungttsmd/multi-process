<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IPMIProcessKill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:kill';

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
        $this->info("🔍 Đang tìm các tiến trình queue:work...");

        // Lấy danh sách PID các tiến trình PHP đang chạy queue:work
        $cmd = 'powershell -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq \'php.exe\' -and $_.CommandLine -match \'queue:work\' } | Select-Object ProcessId, CommandLine"';
        $p = Process::fromShellCommandline($cmd);
        $p->run();

        $output = trim($p->getOutput());

        if (empty($output)) {
            $this->warn("Không có tiến trình queue:work nào đang chạy.");
            return;
        }

        $this->line($output);

        // Lấy danh sách PID
        preg_match_all('/^\s*(\d{2,6})\s+/m', $output, $matches);
        $pids = $matches[1] ?? [];

        if (empty($pids)) {
            $this->warn("Không tìm thấy PID hợp lệ.");
            return;
        }

        $this->info("Đã phát hiện " . count($pids) . " tiến trình PHP queue:work.");
        $this->line("PID: " . implode(', ', $pids));

        // Kill từng tiến trình
        foreach ($pids as $pid) {
            $killCmd = "powershell -Command \"Stop-Process -Id $pid -Force\"";
            $k = Process::fromShellCommandline($killCmd);
            $k->run();

            if ($k->isSuccessful()) {
                $this->info("Đã dừng tiến trình PID: $pid");
            } else {
                $this->error("Không thể dừng PID: $pid");
            }
        }

        $this->info("Tất cả tiến trình queue:work đã được dừng!");
    }
}
