<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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
        $this->newLine(12);
        $this->info('====== KHỞI TẠO ======');
        $this->init();

        do {
            try {
                $this->newLine(12);
                $this->info('====== VÒNG LẶP LẤY DỮ LIỆU ======');
                $count++;
                $this->info("\n=== Chu kỳ $count ===");

                // Lấy sensor (song song)
                $this->info("-> Khởi động lấy sensors lần $count");
                $sensorProcess = new Process(['php', 'artisan', 'ipmi:sensor', 'all']);
                $sensorProcess->start();

                // Lấy status (song song)
                $this->info("-> Khởi động lấy power lần $count");
                $statusProcess = new Process(['php', 'artisan', 'ipmi:power', 'all']);
                $statusProcess->start();

                // Đợi 12 giây trước khi đẩy dữ liệu Redis
                sleep(12);

                // Đẩy dữ liệu Redis vào DB
                $this->info("-> Khởi động đẩy dữ liệu Redis cache vào database lần $count");
                $redisProcess = new Process(['php', 'artisan', 'ipmi:redis']);
                $redisProcess->start();

                // Nghỉ 12 giây trước khi xoá
                sleep(12);

                $this->newLine(12);
                $this->info('====== DỌN DẸP BỘ NHỚ ======');
                if ($count % 4 === 0) {
                $this->flush();
                } else {
                    $this->info('Chưa được 4 chu trình: không cần dọn dẹp');
                }
                $this->info("Hoàn thành cập nhật dữ liệu IPMI lần $count");

            } catch (\Throwable $e) {
                $this->error("Lỗi: " . $e->getMessage());
            }
        }  while (true);
    }

    public function init() {
          $this->info("=== Khởi động dịch vụ IPMI Control ===");

        // === Khởi động các dịch vụ chính ===
        $this->info("-> Khởi động đa tiến trình...");
        Artisan::call('ipmi:run');
        $this->info("Khởi động đa tiến trình [DONE]");

        $this->info("-> Khởi động Redis cache...");
        Artisan::call('redis:run');
        $this->info("Khởi động Redis cache [DONE]");
    }

    public function flush(){
        // === Redis Queue ===
        try {
            $redis = Redis::connection();
            $prefix = Config::get('database.redis.options.prefix', '');
            $keys = $redis->keys("{$prefix}queues:*");

            if (empty($keys)) {
                $this->info('Không có queue nào để xoá.');
            }

            foreach ($keys as $key) {
                $redis->del($key);
            }

            $this->info('Đã xoá toàn bộ Redis queue.');
        } catch (\Throwable $e) {
            $this->error('Lỗi khi xoá Redis queue: ' . $e->getMessage());
        }

        // Xóa dữ liệu
        Redis::flushall();
        $this->info('Đã xoá toàn bộ dữ liệu trong Redis.');

        // === Database Queue ===
        try {
            $count = DB::table('jobs')->delete();
            $this->info("Đã xoá {$count} job khỏi bảng jobs thành công.");
        } catch (\Throwable $e) {
            $this->error('Lỗi khi xoá database queue: ' . $e->getMessage());
        }
    }
}
