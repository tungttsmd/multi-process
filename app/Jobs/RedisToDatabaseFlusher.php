<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisToDatabaseFlusher implements ShouldQueue
{
    use Queueable;

    protected $channelLogFile = 'redis_update_error_log';
    protected $host_ip;

    public function __construct($ip)
    {
        $this->host_ip = $ip;
    }

    public function handle(): void
    {
        try {
            $sensorKey = "ipmi_sensor:" . str_replace('.', '_', $this->host_ip);
            $statusKey = "ipmi_status:" . str_replace('.', '_', $this->host_ip);

            $sensorRawList = Redis::lrange($sensorKey, 0, -1);
            $statusRawList = Redis::lrange($statusKey, 0, -1);

            if (empty($sensorRawList) && empty($statusRawList)) {
                Log::channel($this->channelLogFile)->info("[$this->host_ip] Không có dữ liệu trong Redis");
                return;
            }

            // Lấy phần tử cuối (mới nhất)
            $sensorRaw = end($sensorRawList);
            $statusRaw = end($statusRawList);

            $sensorData = $sensorRaw ? json_decode($sensorRaw, true) : null;
            $statusData = $statusRaw ? json_decode($statusRaw, true) : null;

            if ($sensorRaw && !$sensorData) {
                Log::channel($this->channelLogFile)->warning("[$this->host_ip] JSON sensor lỗi: $sensorRaw");
            }

            if ($statusRaw && !$statusData) {
                Log::channel($this->channelLogFile)->warning("[$this->host_ip] JSON status lỗi: $statusRaw");
            }

            // Cập nhật sensors table
            if (is_array($sensorData)) {
                $this->updateTable('sensors', $sensorData);
            }

            // Cập nhật statuses table
            if (is_array($statusData)) {
                $this->updateTable('statuses', $statusData);
            }

            // Sau khi flush xong, có thể xóa Redis key để tránh dữ liệu cũ (tùy chọn)
            // Redis::del($sensorKey);
            // Redis::del($statusKey);

        } catch (\Throwable $e) {
            Log::channel($this->channelLogFile)->error(sprintf(
                "[%s] Lỗi RedisToDatabaseFlusher: %s (File: %s, Line: %d)",
                $this->host_ip,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }

    protected function updateTable(string $table, array $data): void
    {
        $exists = DB::table($table)->where('ip', $this->host_ip)->exists();

        if ($exists) {
            DB::table($table)
                ->where('ip', $this->host_ip)
                ->update([
                    'log' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        } else {
            Log::channel($this->channelLogFile)->warning("[$this->host_ip] Không tìm thấy IP trong bảng $table");
        }
    }
}
