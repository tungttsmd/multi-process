<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataSyncer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $host_ip;
    protected string $queue_worker;

    public function __construct(string $ip, string $queue_worker)
    {
        $this->host_ip = $ip;
        $this->queue_worker = $queue_worker;
    }

    public function handle(): void
    {
        $ipKey = str_replace('.', '_', $this->host_ip);
        $sensorKey = "ipmi_sensor:{$ipKey}";
        $powerKey  = "ipmi_power:{$ipKey}";
            Log::channel('redis_update_error_log')->info("queue_running:{$this->queue_worker}");

        try {
            Log::channel('redis_update_error_log')->info("queue_running:{$this->queue_worker}");

            if (empty($this->getCache($sensorKey)) && empty($this->getCache($powerKey))) {
                Log::channel('redis_update_error_log')->info("queue_fail:{$this->queue_worker}");
                return;
            }

            $this->updateSensor($this->getCache($sensorKey));
            $this->updatePower($this->getCache($powerKey));

            Cache::increment("queue_done:{$this->queue_worker}");
            Log::channel('redis_update_error_log')->info("queue_done:{$this->queue_worker}");

        } catch (\Throwable $e) {
        }
    }

    protected function getCache($key) {
        return Cache::get($key);
    }


    protected function updateSensor(?string $raw): void
    {
        if (empty($raw)) {
            return;
        }

        $data = json_decode($raw);
        if ($data && ($data->status ?? '') === 'success') {
            DB::table('sensors')->updateOrInsert(
                ['ip' => $this->host_ip],
                [
                    'log' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function updatePower(?string $raw): void
    {
        if (empty($raw)) {
            return;
        }

        $data = json_decode($raw);
        if ($data) {
            DB::table('powers')->updateOrInsert(
                ['ip' => $this->host_ip],
                [
                    'log' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
