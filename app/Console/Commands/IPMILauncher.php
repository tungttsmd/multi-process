<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class IPMILauncher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:launch';

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

        $count = 0;
        $this->info("=== Khởi động dịch vụ IPMI Control ===");

        // === Khởi động các dịch vụ chính ===
        $this->info("-> Khởi động đa tiến trình...");
        Artisan::call('ipmi:run');
        $this->info("Khởi động đa tiến trình [DONE]");

        $this->info("-> Khởi động Redis cache...");
        Artisan::call('redis:run');
        $this->info("Khởi động Redis cache [DONE]");

        // === Vòng lặp chính ===
        $this->info("=== Bắt đầu vòng lặp lấy dữ liệu IPMI ===");

        do {
            try {
                 $count++;
                $this->info("\n=== Chu kỳ $count ===");

                // Lấy sensor (song song)
                $this->info("-> Khởi động lấy sensors lần $count");
                $sensorProcess = new Process(['php', 'artisan', 'ipmi:sensor', 'all']);
                $sensorProcess->start();

                // Lấy status (song song)
                $this->info("-> Khởi động lấy status lần $count");
                $statusProcess = new Process(['php', 'artisan', 'ipmi:status', 'all']);
                $statusProcess->start();

                // Đợi 6.6 giây trước khi đẩy dữ liệu Redis
                sleep(6.6);

                // Đẩy dữ liệu Redis vào DB
                $this->info("-> Khởi động đẩy dữ liệu Redis cache vào database lần $count");
                $redisProcess = new Process(['php', 'artisan', 'ipmi:redis']);
                $redisProcess->start();

                // Nghỉ 12.12 giây trước khi lặp lại
                sleep(12.12);

                $this->info("Hoàn thành cập nhật dữ liệu IPMI lần $count");

            } catch (\Throwable $e) {
                $this->error("Lỗi: " . $e->getMessage());
            }
        }  while (true);
    }
}
