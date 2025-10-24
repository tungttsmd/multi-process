<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisToDatabase implements ShouldQueue
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

            // Log::channel($this->channelLogFile)->info('Sensor: ' . $sensorRaw);
            // Log::channel($this->channelLogFile)->info('Status: ' . $statusRaw);

            $status = json_decode($sensorRaw)->status;

            if ($status === 'success') {
            DB::table('sensors')
                ->where('ip', $this->host_ip)
                ->update([
                    'log' => json_encode($sensorRaw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'updated_at' => now(),
                ]);
            };

            DB::table('statuses')
                ->where('ip', $this->host_ip)
                ->update([
                    'log' => json_encode($statusRaw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'updated_at' => now(),
                ]);



            // Log::channel($this->channelLogFile)->info('Status: ' . $statusRaw);
            // Cập nhật sensors table
            // $this->updateTable('sensors', $sensorData);
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

    // protected function updateTable(string $table, string $data): void
    // {
    //     try {
    //         $exists = DB::table($table)->where('ip', $this->host_ip)->exists();

    //         // Ép toàn bộ data thành JSON để lưu vào 1 cột duy nhất

    //         if ($exists) {
    //             DB::table($table)
    //                 ->where('ip', $this->host_ip)
    //                 ->update([
    //                     'log' => $data,
    //                     'updated_at' => now(),
    //                 ]);
    //         } else {
    //             // Nếu chưa có, thêm mới
    //             DB::table($table)->insert([
    //                 'ip' => $this->host_ip,
    //                 'log' => $data,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);
    //         }
    //     } catch (\Throwable $e) {
    //         Log::channel($this->channelLogFile)->error(sprintf(
    //             "[%s][%s] Lỗi updateTable: %s (Line %d)",
    //             $this->host_ip,
    //             $table,
    //             $e->getMessage(),
    //             $e->getLine()
    //         ));
    //     }
    // }
    protected function makeResponse(string $status, string $message, array|string $data = ''): string
    {
        return json_encode([
            'ip'        => $this->host_ip,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'status'    => $status,
            'message'   => $message,
            'data'      => empty($data) || $data === '' ? new \stdClass() : $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
