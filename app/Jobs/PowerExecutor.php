<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class PowerExecutor implements ShouldQueue
{
    use Queueable;

    protected $command;
    protected $channelLogFile;
    protected $redisKey;

    /**
     * Create a new job instance.
     */
    public function __construct($host_ip, $username, $password, $action)
    {
        if (in_array($action, ['on', 'off', 'reset', 'rs'])) {
            if ($action == 'rs') {
                $action = 'reset';
            };
            $this->command = [
                'ipmitool', // Execute file của IPMI
                '-I', // Interface (lan/lanplus,usb...)
                'lanplus',
                '-H', // Host
                $host_ip,
                '-U', // User
                $username,
                '-P', // Password
                $password,
                'chassis',
                'power',
                $action, // Lệnh thực thi
            ];
        } else {
            // Log::channel($this->channelLogFile)->info("Action không hợp lệ");
            Redis::rpush($this->redisKey, "Action không hợp lệ");
            throw new \Exception("Action không hợp lệ");
        }

        $this->channelLogFile = "host_power_log"; // File ở storage/logs/host_sensor_log.log
        // Log::channel($this->channelLogFile)->info("Command: " . implode(' ', $this->command));

        $hostRedisFormat = str_replace('.', '_', $host_ip); // 203.113.131.1 chuyển sang format dễ xử lí 203_113_131_1
        $this->redisKey = "ipmi:power:host:$hostRedisFormat"; // Key Redis cần đặt để lưu vào Memory
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Chạy sensor command
            $p = new Process($this->command);
            $p->run();
            // $output = $p->getOutput();
            $output = sprintf(
                "Successfully: %s",
                implode(' ', $this->command)
            );

            // Ghi log và đệm vào Redis
            Redis::rpush($this->redisKey, $output);
            // Log::channel($this->channelLogFile)->info($output);
        } catch (\Exception $e) {
            // Log::channel($this->channelLogFile)->error($e->getMessage());
            Redis::rpush($this->redisKey, $e->getMessage());
        }
    }
}
