<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class SensorCrawler implements ShouldQueue
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
            'sensor',
            'reading',
            'CPU0_Temp',
            'CPU1_Temp',
            'CPU0_FAN',
            'CPU1_FAN' // Lệnh thực thi

        ];
        $this->channelLogFile = "host_sensor_log"; // File ở storage/logs/host_sensor_log.log
        // Log::channel($this->channelLogFile)->info("Command: " . implode(' ', $this->command));

        $hostRedisFormat = str_replace('.', '_', $this->host_ip); // 203.113.131.1 chuyển sang format dễ xử lí 203_113_131_1
        $this->redisKey = "ipmi:sensor:host:$hostRedisFormat"; // Key Redis cần đặt để lưu vào Memory
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $p = new Process($this->command);
        $p->setTimeout(2.6);
        try {
            // Chạy sensor command
            $p->run();
            $output = $this->convertOutputJson($p->getOutput());

            // Ghi log và đệm vào Redis
            Log::channel($this->channelLogFile)->info($output);
            Redis::rpush($this->redisKey, $output);
        } catch (\Exception $e) {
            Log::channel($this->channelLogFile)->error($e->getMessage());
            Redis::rpush($this->redisKey, $e->getMessage());
        }
    }
    protected function convertOutputJson($string)
    {
        $string = trim($string);

        // Nếu chuỗi rỗng hoặc toàn lỗi => trả về JSON cảnh báo
        if ($string === '' || stripos($string, 'Error') !== false) {
            return json_encode([
                'ip'   => $this->host_ip,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'status'    => 'error',
                'message'   => $string ?: 'No sensor data',
            ], JSON_UNESCAPED_UNICODE);
        }

        $lines = explode("\n", $string);
        $data = [
            'ip'   => $this->host_ip,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'status'    => 'success',
        ];

        $validCount = 0;
        foreach ($lines as $line) {
            if (strpos($line, '|') !== false) {
                [$key, $value] = array_map('trim', explode('|', $line, 2));
                if ($key !== '' && $value !== '') {
                    $data[$key] = is_numeric($value) ? (float)$value : $value;
                    $validCount++;
                }
            }
        }

        // Nếu không có dữ liệu hợp lệ, vẫn đánh dấu là error
        if ($validCount === 0) {
            $data['status']  = 'error';
            $data['message'] = 'No valid sensor lines found';
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
