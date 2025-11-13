<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class QueueMonitorService
{
    /**
     * Theo dõi danh sách queue bạn quan tâm
     * @var array
     */
    protected array $queues;

    public function __construct(array $queues = [])
    {
        // Nếu không truyền thì mặc định 10 queue sensor đầu tiên
        $this->queues = $queues ?: collect(range(1, 10))
            ->map(fn($i) => "processor_sensor_{$i}")
            ->toArray();
    }

    /**
     * Lấy trạng thái từng queue (đang có bao nhiêu job chờ)
     */
    public function getQueueStatus(): array
    {
        $result = [];

        foreach ($this->queues as $queue) {
            $count = $this->countJobs($queue);
            $result[$queue] = [
                'pending'  => $count,
                'failed'   => $this->countFailed($queue),
                'lock_key' => $this->hasLock($queue),
            ];
        }

        return $result;
    }

    /**
     * Đếm job đang chờ trong queue
     */
    protected function countJobs(string $queue): int
    {
        if (config('queue.default') === 'database') {
            return DB::table('jobs')->where('queue', $queue)->count();
        }

        // Redis queue (laravel Horizon style)
        $key = "queues:{$queue}";
        return Redis::llen($key);
    }

    /**
     * Đếm job lỗi trong bảng failed_jobs
     */
    protected function countFailed(string $queue): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')->where('queue', $queue)->count();
    }

    /**
     * Kiểm tra lock key của queue có tồn tại (đang bị block)
     */
    protected function hasLock(string $queue): bool
    {
        $pattern = "laravel_cache_ipmi_sensor_dispatch_lock:ip:*";
        $keys = Redis::keys($pattern);
        return !empty($keys);
    }

    /**
     * In ra kết quả gọn gàng
     */
    public function printSummary(): void
    {
        $statuses = $this->getQueueStatus();
        echo str_repeat('-', 80) . PHP_EOL;
        echo str_pad('QUEUE', 30) . str_pad('PENDING', 10) . str_pad('FAILED', 10) . "LOCK" . PHP_EOL;
        echo str_repeat('-', 80) . PHP_EOL;

        foreach ($statuses as $queue => $info) {
            echo str_pad($queue, 30)
                . str_pad($info['pending'], 10)
                . str_pad($info['failed'], 10)
                . ($info['lock_key'] ? '🔒' : ' ') . PHP_EOL;
        }
    }
}
