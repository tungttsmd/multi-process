<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
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
    protected $lock_token; // Dùng để tái tạo đúng key trong redis, để thực hiện gỡ key khi xong.

    public function __construct($host_ip, $username, $password, $lock_token)
    {
        $this->host_ip  = $host_ip;
        $this->username = $username;
        $this->password = $password;
        $this->lock_token = $lock_token;

        $this->command = [
            'ipmitool',
            '-I',
            'lanplus',
            '-H',
            $this->host_ip,
            '-U',
            $this->username,
            '-P',
            $this->password,
            'sensor',
            'reading',
            'CPU0_Temp',
            'CPU1_Temp',
            'CPU0_FAN',
            'CPU1_FAN',
        ];

        $this->channelLogFile = 'host_sensor_log';
        $hostRedisFormat      = str_replace('.', '_', $this->host_ip);
        $this->redisKey       = "ipmi_sensor:$hostRedisFormat";
    }

    public function handle(): void
    {
        $lock_key = "ipmi_sensor_dispatch_lock:ip:$this->host_ip";
        $lock_cache = Cache::restoreLock($lock_key, $this->lock_token);

        // Job này chắc chắn đã bị lock ở command dispatch
        // Ở đây ta chỉ cần gỡ key -> ta cần lấy lại đúng cache có lock key đó bằng token
        // token đó tạo ra khi $this->get(), đây là lệnh tạo key đưa vào cache Redis return token
        // Ở command ta sẽ để $token = $this->get()
        // Ở job chỉ cần lấy lại đúng key đúng token đó, khi xóa sẽ xóa được key chính xác mà command đưa xuống
        // Để xóa key đó chỉ cần $lock_cache->release(), lúc này đã trỏ đúng key name và token -> xóa chính xác
         try {
            $p = new Process($this->command);
            $p->setTimeout(6.6);
            $p->run();

            $stderr = trim($p->getErrorOutput());
            $stdout = trim($p->getOutput());

            if (str_contains($stderr, 'exceeded the timeout') || $p->getExitCode() === 143) {
                throw new \RuntimeException("Timeout after {$p->getTimeout()}s (no IPMI response)");
            }

            if (!$p->isSuccessful()) {
                $err = $stderr ?: 'Unknown process error';
                throw new \RuntimeException($err);
            }

            if ($stdout === '' || stripos($stdout, 'timeout') !== false) {
                throw new \RuntimeException('No sensor data or timeout');
            }

            $json = $this->parseOutput($stdout);
            // Log::channel($this->channelLogFile)->info($json);
            Redis::rpush($this->redisKey, $json);
            Log::channel($this->channelLogFile)->info('Đã dispatch sensor: '.$this->host_ip);
        } catch (\Throwable $e) {
            $error = $this->makeResponse('error', $e->getMessage());
            // Log::channel($this->channelLogFile)->error($error);
            Redis::rpush($this->redisKey, $error);
            Log::channel($this->channelLogFile)->info('Thất bại dispatch sensor không rõ lỗi: '.$this->host_ip);
        } finally {
            $release_lock_state = $lock_cache->release();
            Log::channel($this->channelLogFile)->info(var_export($release_lock_state, true). "- Đã xóa key redis của: $lock_key");
        }
    }

    protected function parseOutput(string $rawOutput): string
    {
        $lines = array_filter(explode("\n", trim($rawOutput)));
        $data = [];

        foreach ($lines as $line) {
            if (strpos($line, '|') !== false) {
                [$key, $value] = array_map('trim', explode('|', $line, 2));
                if ($key !== '' && $value !== '') {
                    $data[$key] = is_numeric($value) ? (float)$value : $value;
                }
            }
        }

        $importantKeys = ['CPU0_Temp', 'CPU1_Temp', 'CPU0_FAN', 'CPU1_FAN'];
        $foundKeys = array_intersect($importantKeys, array_keys($data));

        if (empty($foundKeys)) {
            throw new \RuntimeException('No valid sensor fields found (CPU/FAN missing)');
        }

        return $this->makeResponse('success', 'Sensor data fetched successfully', $data);
    }

    protected function makeResponse(string $status, string $message, array|string $data = ''): string
    {
        return json_encode([
            'ip'        => $this->host_ip,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'status'    => $status,
            'message'   => $message,
            'data'      => empty($data) || $data === '' ? (object) [] : (object) $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function displayName() {
        return 'SensorCrawler ['. $this->host_ip.']';
    }
    public function tags() {
        return ['sensor', $this->host_ip];
    }


}
