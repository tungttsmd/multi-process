<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IPMIRedisRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:run';

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
        $redisPath = base_path('redis/redis-server.exe');

        if (!file_exists($redisPath)) {
            $this->error("Không tìm thấy redis-server.exe tại: {$redisPath}");
            return;
        }

        // Kiểm tra Redis có đang chạy không
        $checkCmd = 'powershell -Command "Get-Process | Where-Object { $_.ProcessName -like \'redis*\' } | Select-Object Id, ProcessName"';
        $check = Process::fromShellCommandline($checkCmd);
        $check->run();
        $output = trim($check->getOutput());

        if ($output) {
            $this->info("Redis đã được khởi động rồi! Thông tin redis:...");
            $this->line($output);
            return;
        }

        // Tạo câu lệnh khởi động
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process ' . $redisPath . ' -WindowStyle Hidden"';

        $this->info("Đang khởi động Redis Server...");

        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if ($process->isSuccessful()) {
            $this->info("Redis Server đã khởi động thành công!");
        } else {
            $this->error("Không thể khởi động Redis.");
            $this->line($process->getErrorOutput());
        }
    }
}
