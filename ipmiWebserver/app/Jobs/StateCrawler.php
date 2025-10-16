<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class StateCrawler implements ShouldQueue
{
    use Queueable;

    protected $command;
    protected $channelLogFile;
    protected $redisKey;
    protected $host_ip;
    protected $username;
    protected $password;

    /**
     * Create a new job instance.
     */
    public function __construct($host_ip, $username, $password)
    {
        $this->host_ip = $host_ip;
        $this->username = $username;
        $this->password = $password;
        $this->command = [
            'ipmitool', // Execute file của IPMI
            '-I', // Interface (lan/lanplus,usb...)
            'lan',
            '-H', // Host
            $this->host_ip,
            '-U', // User
            $this->username,
            '-P', // Password
            $this->password,
            'chassis power state' // Lệnh thực thi
        ];
        $this->channelLogFile = "host_state_log"; // File ở storage/logs/host_power_log.log
        // Log::channel($this->channelLogFile)->info("Command: " . implode(' ', $this->command));

        $hostRedisFormat = str_replace('.', '_', $this->host_ip); // 203.113.131.1 chuyển sang format dễ xử lí 203_113_131_1
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
                "State: ON\nSuccessfully: %s",
                "ipmitool -I lan -H {$this->host_ip} -U {$this->username} -P {$this->password} chassis power state"
            );

            // Ghi log và đệm vào Redis
            Redis::rpush($this->redisKey, $output);
            Log::channel($this->channelLogFile)->info($output);
        } catch (\Exception $e) {
            Log::channel($this->channelLogFile)->error($e->getMessage());
            Redis::rpush($this->redisKey, $e->getMessage());
        }
    }
}
