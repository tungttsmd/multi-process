<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class IPMIProcessFlush extends Command
{
    protected $signature = 'ipmi:flush';
    protected $description = 'Xoá toàn bộ queue đang chờ (Redis hoặc database)';

    public function handle()
    {
        $connection = Config::get('queue.default', 'redis');
        $this->info("Kết nối queue hiện tại: {$connection}");

        if (!$this->confirm('Bạn có chắc muốn xoá toàn bộ queue đang chờ?', false)) {
            $this->warn('Đã huỷ thao tác.');
            return;
        }

        // === Redis Queue ===
        if ($connection === 'redis') {
            try {
                $redis = Redis::connection();
                $prefix = Config::get('database.redis.options.prefix', '');
                $keys = $redis->keys("{$prefix}queues:*");

                if (empty($keys)) {
                    $this->info('Không có queue nào để xoá.');
                    return;
                }

                foreach ($keys as $key) {
                    $redis->del($key);
                }

                $this->info('Đã xoá toàn bộ Redis queue.');
            } catch (\Throwable $e) {
                $this->error('Lỗi khi xoá Redis queue: ' . $e->getMessage());
            }

            return;
        }

        // === Database Queue ===
        if ($connection === 'database') {
            try {
                $count = DB::table('jobs')->delete();
                $this->info("Đã xoá {$count} job khỏi bảng jobs thành công.");
            } catch (\Throwable $e) {
                $this->error('Lỗi khi xoá database queue: ' . $e->getMessage());
            }

            return;
        }

        // === Các driver khác ===
        Artisan::call('queue:clear', ['--force' => true]);
        $this->line(Artisan::output());
    }
}
