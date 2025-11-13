<?php

namespace App\Jobs;

use App\Services\RedisCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class SensorCrawler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $command;
    protected $channelLogFile;
    protected $redisKey;
    protected $host_ip;
    protected $username;
    protected $password;
    protected $queue_worker;

    protected $redis_dispatch_note;
    protected $redis_job_done_count;
    protected $redis_job_done;
    protected $redis_job_data;
    protected $redis_host;

    public function __construct($host_ip, $username, $password, $queue_worker)
    {
        $this->host_ip  = $host_ip;

        $this->username = $username;
        $this->password = $password;

        $this->queue_worker = $queue_worker;

         /**
         * redis key:
         *
         * [
         *  sensor_job_done_count_host::192_153_4_20 => int 1,
         *  sensor_job_done_host:192_153_4_20 => true/false
         *  sensor_data_host:192_153_4_20 => json...
         * ]
         */

        // Format host: 192_153_4_20;
        $this->redis_host = str_replace('.', '_', $this->host_ip);

        // Format key: sensor_job_done_count_host::192_153_4_20
        $this->redis_job_done_count = new RedisCacheService('sensor_job_done_count_host:'.$this->redis_host, $queue_worker);

        // Format key: sensor_job_done_host:192_153_4_20
        $this->redis_job_done = new RedisCacheService('sensor_job_done_host:'.$this->redis_host, $queue_worker);

        // Format key: sensor_data_host:192_153_4_20
        $this->redis_job_data = new RedisCacheService('sensor_data_host:'.$this->redis_host, $queue_worker);

        // Redis dispatch note: dispatch:192_153_4_20
        $this->redis_dispatch_note = new RedisCacheService('dispatch:'.$this->redis_host, $queue_worker);

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
    }

    public function handle(): void
    {

         try {

            $runtime = $this->runtime($this->command, 12);

            // Kiểm tra lỗi
            $validation = $this->runtimeValidation($runtime['stderr'], $runtime['stdout'], $runtime['p']);

            if ($validation !== 'ok'){
                throw new \RuntimeException($validation);
            }

            $this->doneCaching($this->parseOutput($runtime['stdout']));

        } catch (\Throwable $e) {

            throw new \RuntimeException("Handle fail: ". $e->getMessage());
        }
    }

    private function runtime($command, $timeout = 12) {
        $p = new Process($command);
        $p->setTimeout(10);
        $p->run();

        $stderr = trim($p->getErrorOutput());
        $stdout = trim($p->getOutput());
        return [
            'p' => $p,
            'stderr' => $stderr,
            'stdout' => $stdout
        ];
    }

    protected function runtimeValidation($stderr, $stdout, $p) {
        if (str_contains($stderr, 'exceeded the timeout') || $p->getExitCode() === 143) {
           return "Timeout after {$p->getTimeout()}s (no IPMI response)";
        }

        if (!$p->isSuccessful()) {
            return $stderr ?: 'Unknown process error';
        }

        if ($stdout === '' || stripos($stdout, 'timeout') !== false) {
            return 'No sensor data or timeout';
        }

        // Valid không có lỗi
        return 'ok';
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
            'redis_key' => $this->redis_dispatch_note->key(),
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

    protected function doneCaching($data) {

        // Xóa dispatch note
        RedisCacheService::remove($this->redis_dispatch_note->key());
        // Đếm count job theo host
        $this->redis_job_done_count->inc();
        // Set true cho job done theo host
        $this->redis_job_done->set(true);
        // Set dữ liệu vào redis theo host
        $this->redis_job_data->set($data);
    }

}
