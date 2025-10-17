<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IPMIRedisKill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:kill';

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
        $this->info("Đang tìm tiến trình Redis...");

        // Tìm Redis process
        $cmd = 'powershell -Command "Get-Process | Where-Object { $_.ProcessName -like \'redis*\' } | Select-Object Id, ProcessName, Path"';
        $p = Process::fromShellCommandline($cmd);
        $p->run();

        $output = trim($p->getOutput());

        if (empty($output)) {
            $this->warn("Không tìm thấy tiến trình Redis nào đang chạy.");
            return;
        }

        $this->line($output);

        // Lấy danh sách PID (số ở đầu dòng)
        preg_match_all('/^\s*(\d{2,6})\s+/m', $output, $matches);
        $pids = $matches[1] ?? [];

        if (empty($pids)) {
            $this->warn("Không tìm thấy PID hợp lệ.");
            return;
        }

        $this->info("Phát hiện " . count($pids) . " tiến trình Redis đang chạy.");
        $this->line("PID: " . implode(', ', $pids));

        if (!$this->confirm("Bạn có chắc muốn dừng tất cả Redis này?", true)) {
            $this->warn(" Đã huỷ thao tác.");
            return;
        }

        // Kill từng tiến trình
        foreach ($pids as $pid) {
            $killCmd = "powershell -Command \"Stop-Process -Id $pid -Force\"";
            $k = Process::fromShellCommandline($killCmd);
            $k->run();

            if ($k->isSuccessful()) {
                $this->info("Đã dừng Redis PID: $pid");
            } else {
                $this->error("Không thể dừng PID: $pid");
            }

            usleep(150000); // nghỉ 0.15s giữa mỗi lần kill
        }

        $this->newLine();
        $this->info("Tất cả tiến trình Redis đã được dừng!");
    }
}
