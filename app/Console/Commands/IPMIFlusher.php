<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class IPMIFlusher extends Command
{
    protected $signature = 'ipmi:flush';
    protected $description = 'Xoá toàn bộ tiến trình về 0 và queue đang chờ (Redis hoặc database)';

    public function handle()
    {
        $connection = Config::get('queue.default', 'redis');
        $this->info("Kết nối queue hiện tại: {$connection}");

        // Xóa tiến trình
        Artisan::call('ipmi:kill');
        $this->info("Đã kill toàn bộ tiến trình");

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
        try {
            Redis::flushall();
            $this->info('Đã xoá toàn bộ dữ liệu trong Redis.');
        } catch (\Throwable $e) {
            $this->info('Lỗi khi xóa redis (có thể đang không chạy Redis: '. $e->getMessage());
        }

        // Kill redis server
        Artisan::call('redis:kill');
        $this->info("Đã kill redis");

        // === Database Queue ===
        if ($connection === 'database') {
            try {
                $count = DB::table('jobs')->delete();
                $this->info("Đã xoá {$count} job khỏi bảng jobs thành công.");
            } catch (\Throwable $e) {
                $this->error('Lỗi khi xoá database queue: ' . $e->getMessage());
            }
        }

        // === Các driver khác ===
        Artisan::call('queue:clear', ['--force' => true]);
        $this->line(Artisan::output());

        // Kiểm tra lại
        Artisan::call('ipmi:list');
        try {
            // Tìm Redis process
            $cmd = 'powershell -Command "Get-Process | Where-Object { $_.ProcessName -like \'redis*\' } | Select-Object Id, ProcessName, Path"';
            $p = Process::fromShellCommandline($cmd);
            $p->run();

            $output = trim($p->getOutput());

            if (empty($output)) {
                $this->warn("Không tìm thấy tiến trình Redis nào đang chạy.");
            }

            $this->line($output);

            // Lấy danh sách PID (số ở đầu dòng)
            preg_match_all('/^\s*(\d{2,6})\s+/m', $output, $matches);
            $pids = $matches[1] ?? [];

            if (empty($pids)) {
                $this->warn("Không tìm thấy PID hợp lệ.");
            }

            $this->info("Phát hiện " . count($pids) . " tiến trình Redis đang chạy.");
            $this->line("PID: " . implode(', ', $pids));
        } catch (\Throwable $e) {
            $this->info('Lỗi khi tìm kiếm Redis (có thể đã bị xóa)'. $e->getMessage());
        }
    }
}
