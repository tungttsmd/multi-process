<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class StatusCrawler implements ShouldQueue
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
            'lanplus',
            '-H', // Host
            $this->host_ip,
            '-U', // User
            $this->username,
            '-P', // Password
            $this->password,
            'chassis',
            'power',
            'status' // Lệnh thực thi
        ];
        $this->channelLogFile = "host_status_log"; // File ở storage/logs/host_status_log.log
        // Log::channel($this->channelLogFile)->info("Command: " . implode(' ', $this->command));

        $hostRedisFormat = str_replace('.', '_', $this->host_ip); // 203.113.131.1 chuyển sang format dễ xử lí 203_113_131_1
        $this->redisKey = "ipmi:status:host:$hostRedisFormat"; // Key Redis cần đặt để lưu vào Memory
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $p = new Process($this->command);
        $p->setTimeout(1.6);
        try {
            $p->run();
            if ($p->isSuccessful()) {
                $output = $this->convertOutputJson($p->getOutput());
                // Ghi log và đệm vào Redis
                Redis::rpush($this->redisKey, $output);
                Log::channel($this->channelLogFile)->info($output);
            } else {
                $output = $this->convertOutputJson($p->getErrorOutput());
                Log::channel($this->channelLogFile)->info($output);
                Redis::rpush($this->redisKey, $output);
            }
        } catch (\Exception $e) {
            Log::channel($this->channelLogFile)->error($e->getMessage());
            Redis::rpush($this->redisKey, $e->getMessage());
        }
    }
    protected function convertOutputJson($string)
    {
        $output = strtolower(trim($string));
        $power = 'unknown';
        $error = null;

        if (str_contains($output, 'chassis power is on')) {
            $power = 'on';
        } elseif (str_contains($output, 'chassis power is off')) {
            $power = 'off';
        } elseif (str_contains($output, 'reset') || str_contains($output, 'cycle')) {
            $power = 'reset';
        } else {
            $error = trim($string) ?: 'unknown';
        }

        $json = [
            'ip'        => $this->host_ip,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        if ($power !== 'unknown') {
            $json['status'] = 'success';
            $json['power']  = $power;
        } else {
            $json['status'] = 'error';
            $json['error']  = $error;
        }

        return json_encode($json, JSON_UNESCAPED_UNICODE);
    }
}
