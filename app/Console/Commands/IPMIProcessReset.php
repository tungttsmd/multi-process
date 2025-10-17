<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IPMIProcessReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:reset';

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
        // Danh sách queue cần khởi động lại
        $queues =   $queues = array_merge(
            config('queue.processor.sensor'),
            config('queue.processor.status'),
            config('queue.processor.update'),
            config('queue.processor.power')
        );

        $this->info("Đang kiểm tra tiến trình queue:work đang chạy...");

        // ======== 1️⃣ TÌM TOÀN BỘ TIẾN TRÌNH HIỆN TẠI ========
        $cmdList = 'powershell -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq \'php.exe\' -and $_.CommandLine -match \'queue:work\' } | Select-Object ProcessId, CommandLine"';
        $procList = Process::fromShellCommandline($cmdList);
        $procList->run();
        $output = trim($procList->getOutput());

        // Tìm PID ở đầu dòng
        preg_match_all('/^\s*(\d{2,6})\s+/m', $output, $matches);
        $pids = $matches[1] ?? [];

        $this->line($output);
        if (!$this->confirm("Phát hiện " . count($pids) . " tiến trình queue:work. Bạn có muốn dừng toàn bộ trước khi khởi động lại?", true)) {
            $this->warn("Đã hủy thao tác reset.");
            return;
        }

        // ======== 2️⃣ DỪNG CÁC TIẾN TRÌNH CŨ ========
        if ($pids) {
            foreach ($pids as $pid) {
                $killCmd = "powershell -Command \"Stop-Process -Id $pid -Force\"";
                $k = Process::fromShellCommandline($killCmd);
                $k->run();
                if ($k->isSuccessful()) {
                    $this->info("Đã dừng PID: $pid");
                } else {
                    $this->error("Không thể dừng PID: $pid");
                }
                usleep(100000);
            }
        } else {
            $this->info("Không có tiến trình queue:work đang chạy.");
        }

        // ======== 3️⃣ KHỞI ĐỘNG LẠI TOÀN BỘ WORKER ========
        $this->info("\n🚀 Đang khởi động lại các worker...");

        foreach ($queues as $queue) {
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process php -ArgumentList \'artisan queue:work --queue=' . $queue . ' --sleep=1 --tries=3\' -WindowStyle Hidden"';
            $p = Process::fromShellCommandline($cmd);
            $p->run();

            if ($p->isSuccessful()) {
                $this->info("Đã khởi động lại worker: {$queue}");
            } else {
                $this->error("Lỗi khi khởi động worker: {$queue}");
            }

            usleep(200000);
        }

        $this->info("\n🎉 Hoàn tất reset! Toàn bộ queue worker đã được khởi động lại.");
    }
}
