<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DatabaseUpdator implements ShouldQueue
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
            $prefix = config('database.redis.options.prefix', '');
            $sensorKey = "{$prefix}ipmi:sensor:host:" . str_replace('.', '_', $this->host_ip);
            $statusKey = "{$prefix}ipmi:status:host:" . str_replace('.', '_', $this->host_ip);
            Log::channel($this->channelLogFile)->info($sensorKey);
            Log::channel($this->channelLogFile)->info($statusKey);

            return;
            $sensorRaw = Redis::lpop($sensorKey);
            $statusRaw = Redis::lpop($statusKey);

            // Nếu Redis không có dữ liệu
            if (!$sensorRaw && !$statusRaw) {
                Log::channel($this->channelLogFile)->info("[$this->host_ip] Không có dữ liệu trong Redis");
                return;
            }

            // Giải mã JSON
            $sensorData = $sensorRaw ? json_decode($sensorRaw, true) : null;
            $statusData = $statusRaw ? json_decode($statusRaw, true) : null;

            // JSON lỗi
            if ($sensorRaw && !$sensorData) {
                Log::channel($this->channelLogFile)->warning("[$this->host_ip] ⚠ JSON sensor lỗi: $sensorRaw");
            }
            if ($statusRaw && !$statusData) {
                Log::channel($this->channelLogFile)->warning("[$this->host_ip] ⚠ JSON status lỗi: $statusRaw");
            }

            // Chỉ update nếu record tồn tại
            if (is_array($sensorData)) {
                if (DB::table('sensors')->where('ip', $this->host_ip)->exists()) {
                    DB::table('sensors')
                        ->where('ip', $this->host_ip)
                        ->update([
                            'log' => json_encode($sensorData, JSON_UNESCAPED_UNICODE),
                            'updated_at' => now(),
                        ]);
                } else {
                    Log::channel($this->channelLogFile)->warning("[$this->host_ip] Không tìm thấy IP trong bảng sensors");
                }
            }

            if (is_array($statusData)) {
                if (DB::table('statuses')->where('ip', $this->host_ip)->exists()) {
                    DB::table('statuses')
                        ->where('ip', $this->host_ip)
                        ->update([
                            'log' => json_encode($statusData, JSON_UNESCAPED_UNICODE),
                            'updated_at' => now(),
                        ]);
                } else {
                    Log::channel($this->channelLogFile)->warning("[$this->host_ip] Không tìm thấy IP trong bảng statuses");
                }
            }
        } catch (\Throwable $e) {
            Log::channel($this->channelLogFile)->error(
                sprintf(
                    "[%s] Lỗi DatabaseUpdator: %s (File: %s, Line: %d)",
                    $this->host_ip,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            );
        }
    }
}
