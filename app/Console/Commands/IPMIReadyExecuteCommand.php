<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class IPMIReadyExecuteCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ipmi:ready-execute';

    /**
     * The console command description.
     */
    protected $description = 'Khởi chạy các worker cần thiết cho IPMI execute (nếu chưa có)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
       // Gom tất cả queue từ config
        $queueGroups = [
            config('queue.processor.user_power'),
            config('queue.processor.user_sensor'),
            config('queue.processor.user_update'),
            config('queue.processor.user_execute'),
        ];

        $queues = collect($queueGroups)->flatten()->filter()->unique()->values();

        if ($queues->isEmpty()) {
            $this->error('Không tìm thấy queue nào trong config.');
            return Command::FAILURE;
        }

        foreach ($queues as $queue) {
            if ($this->isQueueRunning($queue)) {
                $this->line("Already ran [{$queue} in advance. Continue.");
                continue;
            }
            // Câu lệnh PowerShell tương thích Git Bash
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process php -ArgumentList \'artisan queue:work --queue=' . $queue . ' --sleep=1\' -WindowStyle Hidden"';

            $result = Process::run($cmd);

            if ($result->successful()) {
                $this->info("Đã khởi chạy worker cho queue: {$queue}");
            } else {
                $this->error("Lỗi khi chạy queue: {$queue} | " . $result->errorOutput());
            }
        }

        $this->info("Finish!");
        return Command::SUCCESS;

    }

    private function isQueueRunning(string $queue): bool
    {
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process | '
            . 'Where-Object { $_.Name -eq \'php.exe\' -and $_.CommandLine -match \'queue:work\' -and $_.CommandLine -match \'' . $queue . '\' } | '
            . 'Select-Object -ExpandProperty ProcessId"';

        $result = Process::run($cmd);

        $output = trim($result->output());

        return !empty($output);
    }
}
