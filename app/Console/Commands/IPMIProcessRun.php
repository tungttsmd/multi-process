<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IPMIProcessRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:run';

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

        // Danh sách queue bạn muốn chạy song song
        $queues = array_merge(
            config('queue.processor.sensor'),
            config('queue.processor.power'),
            config('queue.processor.update'),
            config('queue.processor.execute'),
            [
                config('queue.processor.user_power'),
                config('queue.processor.user_sensor'),
                config('queue.processor.user_update'),
                config('queue.processor.user_execute')
            ]
        );

        $this->info("Bắt đầu khởi động " . count($queues) . " worker...");

        foreach ($queues as $queue) {
            // Câu lệnh PowerShell tương thích Git Bash
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process php -ArgumentList \'artisan queue:work --queue=' . $queue . ' --sleep=1\' -WindowStyle Hidden"';

            $process = Process::fromShellCommandline($cmd);
            $process->run();

            if ($process->isSuccessful()) {
                $this->info("Đã khởi động worker: {$queue}");
            } else {
                $this->error("Lỗi khi khởi động worker: {$queue}");
                $this->line($process->getErrorOutput());
            }
            sleep(1);
        }

        $this->info("Hoàn tất khởi động tất cả worker!");
    }
}
